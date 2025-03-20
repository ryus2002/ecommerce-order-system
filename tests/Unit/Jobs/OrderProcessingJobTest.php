<?php

namespace Tests\Unit\Jobs;

use App\Events\OrderShipped;
use App\Jobs\OrderProcessingJob;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DistributedLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery;

class OrderProcessingJobTest extends TestCase
{
    use RefreshDatabase;
    
    public function testHandleSuccessfully()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $inventory = Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'version' => 1
        ]);
        
        $orderId = Str::uuid()->toString();
        $order = Order::factory()->create([
            'id' => $orderId,
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100,
            'shard_id' => 1
        ]);
        
        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 50
            ]
        ];
        
        $inventoryVersions = [
            $product->id => 1
        ];
        
        // 模擬分散式鎖服務
        $lockService = Mockery::mock(DistributedLockService::class);
        $lockService->shouldReceive('acquire')
            ->once()
            ->with("order:{$orderId}")
            ->andReturn('test-token');
        
        $lockService->shouldReceive('release')
            ->once()
            ->with("order:{$orderId}", 'test-token')
            ->andReturn(true);
        
        // 模擬事件分發
        Event::fake();
        
        // 執行任務
        $job = new OrderProcessingJob($orderId, $items, $inventoryVersions);
        $job->handle($lockService);
        
        // 驗證結果
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'processed'
        ]);
        
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 8, // 原始數量10減去訂購數量2
            'version' => 2 // 版本增加
        ]);
        
        // 驗證事件
        Event::assertDispatched(OrderShipped::class, function ($event) use ($orderId) {
            return $event->orderId === $orderId;
        });
    }
    
    public function testHandleWithLockAcquisitionFailure()
    {
        // 準備測試數據
        $orderId = Str::uuid()->toString();
        $items = [['product_id' => 1, 'quantity' => 2]];
        $inventoryVersions = [1 => 1];
        
        // 模擬分散式鎖服務 - 無法獲取鎖
        $lockService = Mockery::mock(DistributedLockService::class);
        $lockService->shouldReceive('acquire')
            ->once()
            ->with("order:{$orderId}")
            ->andReturn(null);
        
        // 模擬job的release方法
        $job = Mockery::mock(OrderProcessingJob::class, [$orderId, $items, $inventoryVersions])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        
        $job->shouldReceive('release')
            ->once()
            ->with(30);
        
        // 執行任務
        $job->handle($lockService);
        
        // 添加 PHPUnit 斷言
        $this->assertTrue(true, 'Job was released with 30 seconds delay as expected');
        
        // 使用 Mockery 的 shouldHaveReceived 方法進行額外驗證
        $job->shouldHaveReceived('release')->once()->with(30);
    }
    
    
    public function testHandleWithInventoryVersionConflict()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'version' => 2 // 版本與預期不符
        ]);
        
        $orderId = Str::uuid()->toString();
        Order::factory()->create([
            'id' => $orderId,
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100,
            'shard_id' => 1
        ]);
        
        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 50
            ]
        ];
        
        $inventoryVersions = [
            $product->id => 1 // 預期版本為1，但實際為2
        ];
        
        // 模擬分散式鎖服務
        $lockService = Mockery::mock(DistributedLockService::class);
        $lockService->shouldReceive('acquire')
            ->once()
            ->andReturn('test-token');
        
        $lockService->shouldReceive('release')
            ->once()
            ->andReturn(true);
        
        // 模擬job的release方法
        $job = Mockery::mock(OrderProcessingJob::class, [$orderId, $items, $inventoryVersions])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        
        $job->shouldReceive('release')
            ->once()
            ->with(60);
        
        // 執行任務
        $job->handle($lockService);
        
        // 驗證訂單狀態未變更
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending'
        ]);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
