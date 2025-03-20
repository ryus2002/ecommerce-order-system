<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyShippingDepartment implements ShouldQueue
{
    public function handle(OrderShipped $event): void
    {
        // 通知物流部門訂單已處理完成
        \Log::info("Shipping department notified for order: {$event->orderId}");
    }
}
