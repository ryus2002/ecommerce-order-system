<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 備份現有數據
        $orders = DB::table('orders')->get();
        
        // 刪除舊表
        Schema::dropIfExists('orders');
        
        // 創建新表
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); // 這會創建一個自動遞增的主鍵
            $table->foreignId('user_id')->constrained();
            $table->text('address');
            $table->string('payment_method');
            $table->string('status')->default('pending');
            // 其他欄位...
            $table->timestamps();
        });
        
        // 恢復數據
        foreach ($orders as $order) {
            DB::table('orders')->insert([
                'user_id' => $order->user_id,
                'address' => $order->address ?? '',
                'payment_method' => $order->payment_method ?? '',
                'status' => $order->status ?? 'pending',
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};