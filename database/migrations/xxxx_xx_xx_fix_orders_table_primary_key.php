<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 獲取所有外鍵約束
        $foreignKeys = [];
        $constraints = DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_SCHEMA = DATABASE()
        ");

        // 2. 暫時禁用外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 3. 創建新表 orders_new
        Schema::create('orders_new', function (Blueprint $table) {
            $table->id(); // 自動創建自增主鍵
            $table->foreignId('user_id');
            $table->text('address')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            // 添加其他欄位...
        });

        // 4. 複製數據
        DB::statement("INSERT INTO orders_new (id, user_id, address, payment_method, status, created_at, updated_at)
                       SELECT id, user_id, address, payment_method, status, created_at, updated_at FROM orders");

        // 5. 備份關聯表數據
        $orderItems = DB::table('order_items')->get();

        // 6. 刪除舊表和關聯表
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        // 7. 將新表重命名為舊表名
        Schema::rename('orders_new', 'orders');

        // 8. 重新創建關聯表
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            // 添加其他欄位...
        });

        // 9. 恢復關聯表數據
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

        // 10. 重新啟用外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 由於這是修復遷移，回滾操作可能會很複雜
        // 在生產環境中，您可能不希望回滾此遷移
    }
};