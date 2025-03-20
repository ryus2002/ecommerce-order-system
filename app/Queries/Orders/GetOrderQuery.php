<?php

namespace App\Queries\Orders;

class GetOrderQuery
{
    public function __construct(public string $orderId) {}
}
