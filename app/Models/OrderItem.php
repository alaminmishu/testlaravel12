<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_uid',
        'product_name',
        'category_uid',
        'category_name',
        'seller_uid',
        'seller_name',
        'warehouse_uid',
        'warehouse_name',
        'color_family',
        'size',
        'quantity',
        'mrp_price',
        'discount_amount',
        'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'mrp_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
