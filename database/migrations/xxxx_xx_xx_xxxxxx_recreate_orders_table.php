<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 备份现有数据
        $orders = DB::table('orders')->get();
        
        // 备份订单项目数据
        $orderItems = DB::table('order_items')->get();
        
        // 删除订单项目表
        Schema::dropIfExists('order_items');
        
        // 删除旧表
        Schema::dropIfExists('orders');
        
        // 创建新表
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary(); // 使用UUID作为主键
            $table->foreignId('user_id')->constrained();
            $table->text('address')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->nullable(); // 添加总金额字段
            $table->integer('shard_id')->nullable(); // 添加分片ID字段
            $table->timestamps();
        });
        
        // 重新创建订单项目表
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders');
        });
        
        // 恢复订单数据
        foreach ($orders as $order) {
            $data = [
                'id' => $order->id ?? Str::uuid()->toString(),
                'user_id' => $order->user_id,
                'status' => $order->status ?? 'pending',
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
            
            // 只有当这些属性存在时才添加
            if (property_exists($order, 'address')) {
                $data['address'] = $order->address;
    }
            
            if (property_exists($order, 'payment_method')) {
                $data['payment_method'] = $order->payment_method;
            }
            
            if (property_exists($order, 'total_amount')) {
                $data['total_amount'] = $order->total_amount;
            }
            
            if (property_exists($order, 'shard_id')) {
                $data['shard_id'] = $order->shard_id;
            }
            
            DB::table('orders')->insert($data);
        }
        
        // 恢复订单项目数据
        foreach ($orderItems as $item) {
            DB::table('order_items')->insert([
                'id' => $item->id,
                'order_id' => $item->order_id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};