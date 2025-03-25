<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users and products
        $users = User::all();
        $products = Product::all();
        
        // Possible order statuses
        $statuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        // Payment methods
        $paymentMethods = ['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];
        
        // Create 50 orders with random data
        for ($i = 0; $i < 50; $i++) {
            // Select a random user
            $user = $users->random();
            
            // Generate a random date within the last 30 days
            $date = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            // Create order
            $orderId = Str::uuid()->toString();
            
            // Calculate shard ID (example: using user_id modulo 10)
            $shardId = $user->id % 10;
            
            $order = [
                'id' => $orderId,
                'user_id' => $user->id,
                'address' => $this->generateRandomAddress(),
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'status' => $statuses[array_rand($statuses)],
                'shard_id' => $shardId,
                'created_at' => $date,
                'updated_at' => $date
            ];
            
            // Insert order directly using DB facade since we're using UUID
            DB::table('orders')->insert($order);
            
            // Generate between 1 and 5 order items
            $itemCount = rand(1, 5);
            $totalAmount = 0;
            
            // Use a subset of products to avoid duplicates
            $orderProducts = $products->random($itemCount);
            
            foreach ($orderProducts as $product) {
                $quantity = rand(1, 3);
                $unitPrice = $product->price;
                $totalAmount += ($quantity * $unitPrice);
                
                // Create order item
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
            }
            
            // Update order with total amount
            DB::table('orders')
                ->where('id', $orderId)
                ->update(['total_amount' => $totalAmount]);
        }
    }
    
    /**
     * Generate a random address.
     */
    private function generateRandomAddress(): string
    {
        $streets = [
            '123 Main St', '456 Elm St', '789 Oak Ave', '101 Pine Rd', '202 Maple Blvd',
            '303 Cedar Ln', '404 Birch Dr', '505 Walnut Ct', '606 Cherry Way', '707 Spruce Path'
        ];
        
        $cities = [
            'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
            'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'
        ];
        
        $states = [
            'NY', 'CA', 'IL', 'TX', 'AZ', 'PA', 'TX', 'CA', 'TX', 'CA'
        ];
        
        $zipCodes = [
            '10001', '90001', '60601', '77001', '85001',
            '19101', '78201', '92101', '75201', '95101'
        ];
        
        $index = rand(0, 9);
        
        return $streets[rand(0, 9)] . ', ' . $cities[$index] . ', ' . $states[$index] . ' ' . $zipCodes[$index];
    }
}