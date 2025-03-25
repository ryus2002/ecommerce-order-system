<?php

namespace Tests\Unit\Services;

use App\Events\OrderCreated;
use App\Jobs\OrderProcessingJob;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private OrderService $orderService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }
    
    public function testCreateOrder()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'version' => 1
        ]);
        
        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100
            ]
        ];
        
        $totalAmount = 200;
        
        // 模擬事件和隊列
        Event::fake();
        Queue::fake();
        
        // 執行測試
        $order = $this->orderService->createOrder($user->id, $items, $totalAmount);
        
        // 驗證結果
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals($totalAmount, $order->total_amount);
        $this->assertEquals($user->id, $order->user_id);
        
        // 验证订单项目（使用手动设置的关系）
        $this->assertCount(1, $order->items);
        $orderItem = $order->items->first();
        $this->assertEquals($product->id, $orderItem->product_id);
        $this->assertEquals(2, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->unit_price);
        
        // 驗證事件和隊列
        Event::assertDispatched(OrderCreated::class);
        Queue::assertPushed(OrderProcessingJob::class);
}

    // 其他测试方法...
}