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
        // 检查数据库驱动类型
        $driver = DB::connection()->getDriverName();
        
        // 检查orders表是否存在
        if (Schema::hasTable('orders')) {
            // 获取orders表的所有列名
            $columns = [];
            if ($driver === 'mysql') {
                $columnsQuery = DB::select("SHOW COLUMNS FROM orders");
                foreach ($columnsQuery as $column) {
                    $columns[] = $column->Field;
                }
            } elseif ($driver === 'sqlite') {
                $columnsQuery = DB::select("PRAGMA table_info(orders)");
                foreach ($columnsQuery as $column) {
                    $columns[] = $column->name;
                }
            }
            
            // 只有当列不存在时才添加列
            Schema::table('orders', function (Blueprint $table) use ($columns) {
                if (!in_array('address', $columns)) {
                    $table->text('address')->nullable()->after('user_id');
                }
                if (!in_array('payment_method', $columns)) {
                    $table->string('payment_method')->nullable()->after(in_array('address', $columns) ? 'address' : 'user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 检查orders表是否存在
        if (Schema::hasTable('orders')) {
            // 获取orders表的所有列名
            $driver = DB::connection()->getDriverName();
            $columns = [];
            if ($driver === 'mysql') {
                $columnsQuery = DB::select("SHOW COLUMNS FROM orders");
                foreach ($columnsQuery as $column) {
                    $columns[] = $column->Field;
                }
            } elseif ($driver === 'sqlite') {
                $columnsQuery = DB::select("PRAGMA table_info(orders)");
                foreach ($columnsQuery as $column) {
                    $columns[] = $column->name;
                }
            }
            
            // 只有当列存在时才删除列
            Schema::table('orders', function (Blueprint $table) use ($columns) {
                $columnsToDrop = [];
                if (in_array('address', $columns)) {
                    $columnsToDrop[] = 'address';
                }
                if (in_array('payment_method', $columns)) {
                    $columnsToDrop[] = 'payment_method';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};