<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'The latest iPhone with advanced features and powerful performance.',
                'price' => 999.99
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'High-end Android smartphone with exceptional camera and display.',
                'price' => 899.99
            ],
            [
                'name' => 'MacBook Pro 16"',
                'description' => 'Professional laptop with M3 chip for ultimate performance.',
                'price' => 2499.99
            ],
            [
                'name' => 'Dell XPS 15',
                'description' => 'Premium Windows laptop with stunning display and powerful hardware.',
                'price' => 1799.99
            ],
            [
                'name' => 'iPad Air',
                'description' => 'Versatile tablet for work and entertainment with all-day battery life.',
                'price' => 599.99
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Industry-leading noise cancelling headphones with exceptional sound quality.',
                'price' => 349.99
            ],
            [
                'name' => 'Apple Watch Series 9',
                'description' => 'Advanced health and fitness tracking with seamless iPhone integration.',
                'price' => 399.99
            ],
            [
                'name' => 'Nintendo Switch OLED',
                'description' => 'Versatile gaming console with enhanced display and expanded storage.',
                'price' => 349.99
            ],
            [
                'name' => 'LG C3 OLED 65"',
                'description' => 'Premium OLED TV with stunning picture quality and gaming features.',
                'price' => 1799.99
            ],
            [
                'name' => 'Dyson V15 Detect',
                'description' => 'Powerful cordless vacuum with laser dust detection technology.',
                'price' => 699.99
            ]
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}