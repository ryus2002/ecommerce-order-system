<?php

namespace App\Handlers\Orders;

use App\Commands\Orders\CreateOrderCommand;
use App\Services\OrderService;

class CreateOrderHandler
{
    public function __construct(private OrderService $orderService) {}

    public function handle(CreateOrderCommand $command)
    {
        return $this->orderService->createOrder(
            $command->userId,
            $command->items,
            $command->totalAmount
        );
    }
}
