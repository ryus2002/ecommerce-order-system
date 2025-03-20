<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * 可批量賦值的屬性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'address',
        'payment_method',
        'status',
        'total_amount',
    ];

    /**
     * 訂單關聯的用戶
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 訂單包含的產品
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                    ->withPivot('quantity', 'price')
                    ->withTimestamps();
    }
}