我了解了，以下是基於專案內容進行修改後的 Markdown 內容：

# Laravel 分散式電商訂單處理系統：新手完整指南

本教學將帶領您建立一個高效能的分散式電商訂單處理系統，透過 Laravel 框架實現。我們將複雜的系統拆解成簡單步驟，並加入單元測試，讓新手也能輕鬆完成。

## 目錄
1. [專案設置](#1-專案設置)
2. [資料庫設計](#2-資料庫設計)
3. [建立模型與關聯](#3-建立模型與關聯)
4. [實作分散式鎖服務](#4-實作分散式鎖服務)
5. [訂單服務實作](#5-訂單服務實作)
6. [訂單處理任務](#6-訂單處理任務)
7. [控制器實作](#7-控制器實作)
8. [路由設定](#8-路由設定)
9. [事件與監聽器](#9-事件與監聽器)
10. [單元測試](#10-單元測試)
11. [整合 Swoole 提升效能](#11-整合-swoole-提升效能)
12. [啟動與測試](#12-啟動與測試)
13. [效能測試與監控](#13-效能測試與監控)

## 1. 專案設置

首先，建立一個新的 Laravel 專案：

```bash
composer create-project laravel/laravel ecommerce-order-system
cd ecommerce-order-system
```

安裝必要的套件：

```bash
composer require predis/predis
composer require laravel/sanctum
```

## 2. 資料庫設計

建立必要的資料表遷移檔：

```bash
php artisan make:migration create_products_table
php artisan make:migration create_inventories_table
php artisan make:migration create_orders_table
php artisan make:migration create_order_items_table
```

編輯 database/migrations/xxxx_xx_xx_create_products_table.php：

```php
public function up()
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->timestamps();
    });
}
```

編輯 database/migrations/xxxx_xx_xx_create_inventories_table.php：

```php
public function up()
{
    Schema::create('inventories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->onDelete('cascade');
        $table->integer('quantity')->default(0);
        $table->integer('version')->default(1); // 用於樂觀鎖
        $table->timestamps();
    });
}
```

編輯 database/migrations/xxxx_xx_xx_create_orders_table.php：

```php
public function up()
{
    Schema::create('orders', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignId('user_id')->constrained();
        $table->string('status')->default('pending');
        $table->decimal('total_amount', 10, 2);
        $table->unsignedTinyInteger('shard_id'); // 用於分片
        $table->timestamps();
    });
}
```

編輯 database/migrations/xxxx_xx_xx_create_order_items_table.php：

```php
public function up()
{
    Schema::create('order_items', function (Blueprint $table) {
        $table->id();
        $table->uuid('order_id');
        $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        $table->foreignId('product_id')->constrained();
        $table->integer('quantity');
        $table->decimal('unit_price', 10, 2);
        $table->timestamps();
    });
}
```

執行遷移：

```bash
php artisan migrate
```

## 3. 建立模型與關聯

建立必要的模型：

```bash
php artisan make:model Product
php artisan make:model Inventory
php artisan make:model Order
php artisan make:model OrderItem
```

編輯 app/Models/Product.php：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'price'];

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}
```

編輯 app/Models/Inventory.php：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'version'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```

編輯 app/Models/Order.php：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'user_id', 'status', 'total_amount', 'shard_id'];
    
    protected $keyType = 'string';
    
    public $incrementing = false;

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

編輯 app/Models/OrderItem.php：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```

## 4. 實作分散式鎖服務

建立分散式鎖服務：

```bash
php artisan make:service DistributedLockService
```

編輯 app/Services/DistributedLockService.php：

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DistributedLockService
{
    private $retryCount = 3;
    private $retryDelay = 200; // 毫秒
    private $ttl = 30; // 秒

    public function acquire(string $key, int $ttl = null): ?string
    {
        $token = Str::random(20);
        $ttl = $ttl ?: $this->ttl;
        
        for ($i = 0; $i < $this->retryCount; $i++) {
            // 嘗試獲取鎖
            $acquired = Redis::set($key, $token, 'EX', $ttl, 'NX');
            
            if ($acquired) {
                return $token;
            }
            
            // 等待一段時間後重試
            usleep($this->retryDelay * 1000);
        }
        
        return null; // 無法獲取鎖
    }

    public function release(string $key, string $token): bool
    {
        // 使用Lua腳本確保原子性操作
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";
        
        return (bool) Redis::eval($script, 1, $key, $token);
    }
}
```

## 5. 訂單服務實作

建立訂單服務：

```bash
php artisan make:service OrderService
```

編輯 app/Services/OrderService.php：

```php
<?php

namespace App\Services;

use App\Jobs\OrderProcessingJob;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(int $userId, array $items, float $totalAmount): Order
    {
        // 生成唯一訂單ID
        $orderId = Str::uuid()->toString();
        
        // 計算分片ID (0-3)
        $shardId = crc32($orderId) % 4;
        
        // 開始交易
        return DB::transaction(function () use ($orderId, $userId, $items, $totalAmount, $shardId) {
            // 建立訂單
            $order = Order::create([
                'id' => $orderId,
                'user_id' => $userId,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'shard_id' => $shardId
            ]);
            
            // 獲取商品庫存版本
            $productIds = array_column($items, 'product_id');
            $inventories = DB::table('inventories')
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'version')
                ->get();
            
            $inventoryVersions = [];
            foreach ($inventories as $inventory) {
                $inventoryVersions[$inventory->product_id] = $inventory->version;
            }
            
            // 分派訂單處理任務
            OrderProcessingJob::dispatch($orderId, $items, $inventoryVersions);
            
            return $order;
        });
    }
    
    public function getOrder(string $orderId): ?Order
    {
        return Order::with('items.product')->find($orderId);
    }
}
```

## 6. 訂單處理任務

建立訂單處理任務：

```bash
php artisan make:job OrderProcessingJob
```

編輯 app/Jobs/OrderProcessingJob.php：

```php
<?php

namespace App\Jobs;

use App\Events\OrderShipped;
use App\Services\DistributedLockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderId;
    public $items;
    public $inventoryVersions;
    
    public $tries = 5;
    
    public function __construct(string $orderId, array $items, array $inventoryVersions)
    {
        $this->orderId = $orderId;
        $this->items = $items;
        $this->inventoryVersions = $inventoryVersions;
    }

    public function handle(DistributedLockService $lockService)
    {
        // 獲取分散式鎖
        $lockToken = $lockService->acquire("order:{$this->orderId}");
        
        if (!$lockToken) {
            // 無法獲取鎖，稍後重試
            Log::warning("Cannot acquire lock for order {$this->orderId}");
            $this->release(30); // 30秒後重試
            return;
        }
        
        try {
            // 處理訂單
            $this->processOrder();
        } finally {
            // 釋放鎖
            $lockService->release("order:{$this->orderId}", $lockToken);
        }
    }
    
    private function processOrder()
    {
        DB::transaction(function () {
            // 獲取訂單
            $order = DB::table('orders')->where('id', $this->orderId)->first();
            
            if (!$order || $order->status !== 'pending') {
                return;
            }
            
            // 檢查並更新庫存
            foreach ($this->items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $expectedVersion = $this->inventoryVersions[$productId] ?? null;
                
                if (!$expectedVersion) {
                    continue;
                }
                
                // 使用樂觀鎖更新庫存
                $updated = DB::table('inventories')
                    ->where('product_id', $productId)
                    ->where('version', $expectedVersion)
                    ->where('quantity', '>=', $quantity)
                    ->update([
                        'quantity' => DB::raw("quantity - {$quantity}"),
                        'version' => DB::raw('version + 1')
                    ]);
                
                if (!$updated) {
                    // 版本衝突或庫存不足，稍後重試
                    Log::warning("Inventory version conflict for product {$productId}");
                    $this->release(60); // 60秒後重試
                    return;
                }
            }
            
            // 更新訂單狀態
            DB::table('orders')
                ->where('id', $this->orderId)
                ->update(['status' => 'processed']);
            
            // 建立訂單項目
            foreach ($this->items as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $this->orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // 觸發訂單已處理事件
            event(new OrderShipped($this->orderId));
        });
    }
}
```

## 7. 控制器實作

建立訂單控制器：

```bash
php artisan make:controller API/OrderController
```

編輯 app/Http/Controllers/API/OrderController.php：

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    private $orderService;
    
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    
    public function store(Request $request)
    {
        // 驗證請求
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // 建立訂單
        $order = $this->orderService->createOrder(
            auth()->id(),
            $request->input('items'),
            $request->input('total_amount')
        );
        
        return response()->json([
            'message' => 'Order created successfully',
            'order_id' => $order->id
        ], 201);
    }
    
    public function show(string $id)
    {
        $order = $this->orderService->getOrder($id);
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        
        return response()->json(['order' => $order]);
    }
}
```

## 8. 路由設定

編輯 routes/api.php：

```php
<?php

use App\Http\Controllers\API\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
```

## 9. 事件與監聽器

建立訂單已處理事件：

```bash
php artisan make:event OrderShipped
```

編輯 app/Events/OrderShipped.php：

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderShipped
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderId;

    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }
}
```

建立訂單通知監聽器：

```bash
php artisan make:listener SendOrderShippedNotification --event=OrderShipped
```

編輯 app/Listeners/SendOrderShippedNotification.php：

```php
<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendOrderShippedNotification implements ShouldQueue
{
    public function handle(OrderShipped $event)
    {
        $order = Order::with('user')->find($event->orderId);
        
        if (!$order) {
            return;
        }
        
        // 這裡可以實作發送通知給用戶的邏輯
        Log::info("Order {$order->id} has been shipped. Notifying user {$order->user->email}");
    }
}
```

註冊事件與監聽器，編輯 app/Providers/EventServiceProvider.php：

```php
protected $listen = [
    OrderShipped::class => [
        SendOrderShippedNotification::class,
    ],
];
```

## 10. 單元測試

### 步驟1：建立測試環境

編輯 .env.testing：

```
APP_ENV=testing 
DB_CONNECTION=sqlite 
DB_DATABASE=:memory: 
CACHE_DRIVER=array 
SESSION_DRIVER=array 
QUEUE_CONNECTION=sync
```

### 步驟2：建立訂單服務測試

```bash
php artisan make:test Unit/Services/OrderServiceTest
```

編輯 tests/Unit/Services/OrderServiceTest.php：

```php
<?php

namespace Tests\Unit\Services;

use App\Jobs\OrderProcessingJob;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateOrder()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10
        ]);
        
        $items = [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100
            ]
        ];
        
        // 模擬任務分派
        Bus::fake();
        
        // 執行測試
        $orderService = new OrderService();
        $order = $orderService->createOrder($user->id, $items, 200);
        
        // 驗證結果
        $this->assertNotNull($order->id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(200, $order->total_amount);
        
        // 驗證任務分派
        Bus::assertDispatched(OrderProcessingJob::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });
    }
    
    public function testGetOrder()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'processed',
            'total_amount' => 200
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100
        ]);
        
        // 執行測試
        $orderService = new OrderService();
        $result = $orderService->getOrder($order->id);
        
        // 驗證結果
        $this->assertEquals($order->id, $result->id);
        $this->assertEquals('processed', $result->status);
        $this->assertEquals(1, $result->items->count());
        $this->assertEquals($product->id, $result->items[0]->product_id);
    }
}
```

### 步驟3：建立分散式鎖服務測試

```bash
php artisan make:test Unit/Services/DistributedLockServiceTest
```

編輯 tests/Unit/Services/DistributedLockServiceTest.php：

```php
<?php

namespace Tests\Unit\Services;

use App\Services\DistributedLockService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Mockery;

class DistributedLockServiceTest extends TestCase
{
    public function testAcquireLock()
    {
        // 模擬Redis
        Redis::shouldReceive('set')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::type('string'),
                'EX',
                Mockery::type('int'),
                'NX'
            )
            ->andReturn(true);
            
        $lockService = new DistributedLockService();
        $token = $lockService->acquire('test-key');
        
        $this->assertNotNull($token);
    }
    
    public function testReleaseLock()
    {
        // 模擬Redis
        Redis::shouldReceive('eval')
            ->once()
            ->with(
                Mockery::type('string'),
                1,
                'test-key',
                'test-token'
            )
            ->andReturn(1);
            
        $lockService = new DistributedLockService();
        $result = $lockService->release('test-key', 'test-token');
        
        $this->assertTrue($result);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

### 步驟4：建立訂單控制器測試

```bash
php artisan make:test Feature/Http/Controllers/OrderControllerTest
```

編輯 tests/Feature/Http/Controllers/OrderControllerTest.php：

```php
<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testStoreOrder()
    {
        // 準備測試數據
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10
        ]);
        
        $orderData = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100
                ]
            ],
            'total_amount' => 200
        ];
        
        // 模擬OrderService
        $orderId = Str::uuid()->toString();
        $orderServiceMock = Mockery::mock(OrderService::class);
        $orderServiceMock->shouldReceive('createOrder')
            ->once()
            ->andReturn((object)['id' => $orderId]);
        $this->app->instance(OrderService::class, $orderServiceMock);
        
        // 發送請求
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $orderData);
            
        // 驗證回應
        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Order created successfully',
                'order_id' => $orderId
            ]);
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
```

### 步驟5：實作訂單處理任務測試

```bash
php artisan make:test Unit/Jobs/OrderProcessingJobTest
```

編輯 tests/Unit/Jobs/OrderProcessingJobTest.php：

```php
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
```

### 步驟6：建立工廠類

為了讓測試更加方便，我們需要建立模型工廠：

```bash
php artisan make:factory ProductFactory
php artisan make:factory InventoryFactory
php artisan make:factory OrderFactory
php artisan make:factory OrderItemFactory
```

編輯 database/factories/ProductFactory.php：

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}
```

編輯 database/factories/InventoryFactory.php：

```php
<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition()
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'version' => 1,
        ];
    }
}
```

編輯 database/factories/OrderFactory.php：

```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'processed', 'shipped', 'delivered']),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'shard_id' => $this->faker->numberBetween(0, 3),
        ];
    }
}
```

編輯 database/factories/OrderItemFactory.php：

```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->faker->randomFloat(2, 10, 200),
        ];
    }
}
```

## 11. 整合 Swoole 提升效能

### 步驟1：安裝 Swoole 擴展

在 Ubuntu/Debian 系統上：

```bash
sudo apt-get install php-dev libcurl4-openssl-dev
sudo pecl install swoole
```

在 macOS 上（使用 Homebrew）：

```bash
brew install swoole
```

或者使用 Docker：

```bash
# 在 Dockerfile 中
FROM php:8.1-cli
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && pecl install swoole \
    && docker-php-ext-enable swoole
```

### 步驟2：配置 Laravel Octane

安裝 Laravel Octane：

```bash
composer require laravel/octane
php artisan octane:install
```

選擇 Swoole 作為服務器。

編輯 config/octane.php：

```php
return [
    'server' => env('OCTANE_SERVER', 'swoole'),
    'https' => false,
    'listeners' => [
        'start' => [
            // 啟動時執行的監聽器
        ],
        'request' => [
            // 處理請求時執行的監聽器
        ],
        'task' => [
            // 處理任務時執行的監聽器
        ],
        'worker' => [
            // 當 worker 啟動時執行的監聽器
        ],
    ],
    'warm' => [
        // 預熱的服務
    ],
    'swoole' => [
        'options' => [
            'worker_num' => env('OCTANE_WORKERS', 4),
            'task_worker_num' => env('OCTANE_TASK_WORKERS', 4),
            'max_request' => env('OCTANE_MAX_REQUESTS', 1000),
            'enable_coroutine' => true,
            'hook_flags' => SWOOLE_HOOK_ALL,
        ],
    ],
];
```

### 步驟3：配置 Redis 連接池

為了整合 Swoole 的 RedisPool 功能，我們需要安裝相應的套件：

```bash
composer require shashandr/laravel-swoole-redis
```

編輯 config/swoole_redis.php：

```php
<?php

return [
    'pool' => [
        'min_connections' => env('SWOOLE_REDIS_MIN_CONNECTIONS', 10),
        'max_connections' => env('SWOOLE_REDIS_MAX_CONNECTIONS', 100),
        'connect_timeout' => env('SWOOLE_REDIS_CONNECT_TIMEOUT', 1.0),
        'wait_timeout' => env('SWOOLE_REDIS_WAIT_TIMEOUT', 3.0),
        'heartbeat' => env('SWOOLE_REDIS_HEARTBEAT', -1),
        'max_idle_time' => env('SWOOLE_REDIS_MAX_IDLE_TIME', 60.0),
    ],
];
```

編輯 app/Providers/AppServiceProvider.php：

```php
public function register()
{
    if ($this->app->runningInConsole() === false && extension_loaded('swoole')) {
        $this->app->singleton('redis', function () {
            return new \ShashandR\LaravelSwooleRedis\RedisManager(
                $this->app,
                config('database.redis.client', 'predis'),
                config('database.redis')
            );
        });
    }
}
```

## 12. 啟動與測試

### 步驟1：執行單元測試

```bash
php artisan test
```

### 步驟2：啟動服務

```bash
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
```

### 步驟3：使用 Postman 或其他工具測試 API

建立訂單：

```
POST /api/orders
Authorization: Bearer {token}
Content-Type: application/json

{
    "items": [
        {
            "product_id": 1,
            "quantity": 2,
            "unit_price": 100
        }
    ],
    "total_amount": 200
}
```

查詢訂單：

```
GET /api/orders/{orderId}
Authorization: Bearer {token}
```

## 13. 效能測試與監控

### 步驟1：使用 Apache Benchmark 進行壓力測試

```bash
ab -n 1000 -c 100 -H "Authorization: Bearer {token}" -T "application/json" -p post_data.json http://localhost:8000/api/orders
```

其中 post_data.json 包含訂單資料。

### 步驟2：監控 Redis 效能

使用 Redis CLI 監控命令：

```bash
redis-cli monitor
```

或使用 Redis INFO 命令：

```bash
redis-cli info
```

## 總結

我們已經完成了一個完整的分散式電商訂單處理系統，包含以下關鍵特性：

1. CQRS 模式分離讀寫操作，提高系統擴展性
2. Redis 分散式鎖保證訂單處理的一致性
3. 資料庫分片實現水平擴展
4. Swoole 提供高併發處理能力
5. 樂觀鎖處理庫存更新衝突
6. 事件驅動架構實現系統解耦
7. 完整的單元測試確保程式碼品質

這個系統架構適合高流量電商平台使用，能夠處理大量並發訂單請求，同時保證資料一致性。透過 Swoole 和 Redis 連接池的整合，系統效能得到顯著提升。

對於新手來說，可以按照上述步驟一步步實現，從基本功能開始，逐步添加進階特性，最終建立一個完整的分散式系統。