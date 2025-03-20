<?php

namespace App\Jobs;

use App\Events\OrderShipped;
use App\Models\Inventory;
use App\Models\Order;
use App\Services\DistributedLockService;
use App\Services\ShardingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class OrderProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $orderId,
        private array $items,
        private array $inventoryVersions
    ) {}

    public function handle(DistributedLockService $lockService)
    {
        // 獲取分散式鎖
        $lockToken = $lockService->acquire("order:{$this->orderId}");
        
        if (!$lockToken) {
            // 無法獲取鎖，稍後重試
            $this->release(30);
            return;
        }
        
        try {
            DB::transaction(function () {
                // 處理每個訂單項目
                foreach ($this->items as $item) {
                    // 使用樂觀鎖更新庫存
                    $updated = Inventory::where('product_id', $item['product_id'])
                        ->where('version', $this->inventoryVersions[$item['product_id']])
                        ->decrement('quantity', $item['quantity'], [
                            'version' => DB::raw('version + 1')
                        ]);
                    
                    if (!$updated) {
                        // 樂觀鎖失敗，拋出例外以回滾交易
                        throw new \Exception("Inventory version conflict for product {$item['product_id']}");
                    }
                }
                
                // 更新訂單狀態
                $order = Order::findOrFail($this->orderId);
                $order->status = 'processed';
                $order->save();
            });
            
            // 交易成功後，發送訂單已處理事件
            Event::dispatch(new OrderShipped($this->orderId));
        } catch (\Exception $e) {
            // 處理失敗，記錄錯誤並重試
            \Log::error("Order processing failed: {$e->getMessage()}", [
                'order_id' => $this->orderId,
                'exception' => $e,
            ]);
            
            $this->release(60);
        } finally {
            // 釋放分散式鎖
            $lockService->release("order:{$this->orderId}", $lockToken);
        }
    }
}
