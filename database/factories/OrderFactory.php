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
