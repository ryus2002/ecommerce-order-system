<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Jobs\OrderProcessingJob;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function createOrder(string $userId, array $items, float $totalAmount): Order
    {
        // 檢查庫存並獲取庫存版本
        $inventoryVersions = $this->checkAndGetInventoryVersions($items);
        
        // 創建訂單
        $orderId = Str::uuid()->toString();
        $shardId = ShardingManager::getShardIdFromOrderId($orderId);
        
        // 创建订单
        $order = new Order();
        $order->id = $orderId;
        $order->user_id = $userId;
        $order->status = 'pending';
        $order->total_amount = $totalAmount;
        $order->shard_id = $shardId;
        $order->save();
            
        // 创建订单项目
        $orderItemsCollection = collect();
        foreach ($items as $item) {
            $orderItem = new OrderItem();
            $orderItem->order_id = $orderId;
            $orderItem->product_id = $item['product_id'];
            $orderItem->quantity = $item['quantity'];
            $orderItem->unit_price = $item['unit_price'];
            $orderItem->save();
                
            // 添加到集合中
            $orderItemsCollection->push($orderItem);
        }
        
        // 手动设置订单的items关系
        $order->setRelation('items', $orderItemsCollection);
        // 發送訂單創建事件
        Event::dispatch(new OrderCreated($order));
        
        // 派發訂單處理任務
        OrderProcessingJob::dispatch($orderId, $items, $inventoryVersions);
        
        return $order;
    }
    private function checkAndGetInventoryVersions(array $items): array
    {
        $inventoryVersions = [];
        $insufficientInventory = [];
        
        foreach ($items as $item) {
            $inventory = Inventory::where('product_id', $item['product_id'])->first();
            
            if (!$inventory || $inventory->quantity < $item['quantity']) {
                $insufficientInventory[] = $item['product_id'];
            } else {
                $inventoryVersions[$item['product_id']] = $inventory->version;
            }
        }

        if (!empty($insufficientInventory)) {
            throw new \Exception('Insufficient inventory for products: ' . implode(', ', $insufficientInventory));
        }
        
        return $inventoryVersions;
    }
}