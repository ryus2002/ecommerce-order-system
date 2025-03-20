<?php

namespace App\Commands\Orders;

class CreateOrderCommand
{
    public function __construct(
        public string $userId,
        public array $items,
        public float $totalAmount
    ) {}
}