<?php

namespace App\Handlers\Queries;

use App\Models\Order;
use App\Queries\Orders\GetOrderQuery;

class GetOrderHandler
{
    public function handle(GetOrderQuery $query)
    {
        return Order::with('items')->find($query->orderId);
    }
}
