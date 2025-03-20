<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Artisan;
use App\Models\Order;


class OrderControllerTest extends TestCase
{
    use DatabaseTransactions;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 運行遷移
        Artisan::call('migrate');
    }

    public function testStoreOrder()
    {
        $user = User::factory()->create();
        $orderId = 123;
        
        // 創建一個產品記錄
        $product = \App\Models\Product::factory()->create([
            'price' => 100
        ]);
        
        // 更新測試數據，添加必需的字段
        $orderData = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => $product->price
                ]
            ],
            'total_amount' => 200
        ];
        
        // 創建完整的 Order 實例
        $order = new Order();
        $order->id = $orderId;
        $order->user_id = $user->id;
        $order->total_amount = $orderData['total_amount'];
        $order->status = 'pending';
        
        // 使用 mock
        $orderService = Mockery::mock(OrderService::class);
        
        // 模擬 createOrder 方法，接受三個參數
        $orderService->shouldReceive('createOrder')
            ->with(
                $user->id,  // 第一個參數是用戶 ID
                Mockery::on(function($items) use ($orderData) {
                    // 檢查是否是訂單項目數組
                    return is_array($items) && count($items) > 0;
                }),
                $orderData['total_amount']  // 第三個參數是總金額
            )
            ->once()
            ->andReturn($order);
            
        $this->app->instance(OrderService::class, $orderService);
        
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $orderData);
        
        $response->assertStatus(201);
        
        // 驗證 mock 是否被調用
        $orderService->shouldHaveReceived('createOrder')->once();
    }
    
    public function testStoreOrderWithValidationErrors()
    {
        // 準備測試數據
        $user = User::factory()->create();
        
        $orderData = [
            'items' => [
                [
                    'product_id' => 999, // 不存在的產品ID
                    'quantity' => -1, // 無效的數量
                    'unit_price' => 'invalid' // 無效的價格
                ]
            ],
            'total_amount' => 'invalid' // 無效的總金額
        ];
        
        // 發送請求
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $orderData);
        
        // 驗證回應
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity', 'items.0.unit_price', 'total_amount']);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
