<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        // 發送訂單確認郵件
        \Log::info("Order confirmation email sent for order: {$event->order->id}");
    }
}
