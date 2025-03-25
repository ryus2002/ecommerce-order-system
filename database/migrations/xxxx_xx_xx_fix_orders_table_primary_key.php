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
        
        // 根据数据库驱动类型禁用外键约束
        if ($driver === 'mysql') {
            // MySQL方式禁用外键约束
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            // SQLite方式禁用外键约束
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        // 3. 创建新表 orders_new
        Schema::create('orders_new', function (Blueprint $table) {
            $table->id(); // 自动创建自增主键
            $table->foreignId('user_id');
            $table->text('address')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            // 添加其他字段...
        });

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
            
            // 确定要复制的列（orders表和orders_new表都有的列）
            $commonColumns = array_intersect($columns, ['id', 'user_id', 'address', 'payment_method', 'status', 'created_at', 'updated_at']);
            
            // 如果有共同的列，则复制数据
            if (!empty($commonColumns)) {
                $columnsStr = implode(', ', $commonColumns);
                DB::statement("INSERT INTO orders_new ($columnsStr) SELECT $columnsStr FROM orders");
            }

            // 5. 备份关联表数据（如果order_items表存在）
            $orderItems = [];
            if (Schema::hasTable('order_items')) {
                $orderItems = DB::table('order_items')->get();

                // 6. 删除关联表
                Schema::dropIfExists('order_items');
            }

            // 删除旧表
            Schema::dropIfExists('orders');
        }

        // 7. 将新表重命名为旧表名
        Schema::rename('orders_new', 'orders');

        // 8. 重新创建关联表
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            // 添加其他字段...
        });

        // 9. 恢复关联表数据（如果有）
        if (!empty($orderItems)) {
            foreach ($orderItems as $item) {
                $data = [
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ];
                
                // 只有当有这些属性时才添加
                if (property_exists($item, 'id')) {
                    $data['id'] = $item->id;
                }
                if (property_exists($item, 'created_at')) {
                    $data['created_at'] = $item->created_at;
                }
                if (property_exists($item, 'updated_at')) {
                    $data['updated_at'] = $item->updated_at;
                }
                
                DB::table('order_items')->insert($data);
            }
        }

        // 10. 重新启用外键约束
        if ($driver === 'mysql') {
            // MySQL方式启用外键约束
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            // SQLite方式启用外键约束
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 由于这是修复迁移，回滚操作可能会很复杂
        // 在生产环境中，您可能不希望回滚此迁移
    }
};