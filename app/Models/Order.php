<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'uid',
        'environment',
        'status',
        'payment_method',
        'payment_status',
        'created_at_external',
        'updated_at_external',
        'currency',
        'total_amount',
        'customer_email',
        'raw',
    ];

    protected $casts = [
        'created_at_external' => 'datetime',
        'updated_at_external' => 'datetime',
        'raw' => 'array',
        'total_amount' => 'decimal:2',
    ];

    // If later you add OrderItem:
    // public function items() { return $this->hasMany(OrderItem::class); }

    // Handy scopes (optional — great for Filament filters)
    public function scopeStatus($q, ?string $status)
    {
        return $q->when($status, fn ($qq) => $qq->where('status', $status));
    }

    public function scopePaymentMethod($q, ?string $method)
    {
        return $q->when($method, fn ($qq) => $qq->where('payment_method', $method));
    }

    public function scopeCreatedBetween($q, ?string $from, ?string $to)
    {
        return $q
            ->when($from, fn ($qq) => $qq->where('created_at_external', '>=', $from))
            ->when($to,   fn ($qq) => $qq->where('created_at_external', '<=', $to));
    }

    public function scopeUpdatedBetween($q, ?string $from, ?string $to)
    {
        return $q
            ->when($from, fn ($qq) => $qq->where('updated_at_external', '>=', $from))
            ->when($to,   fn ($qq) => $qq->where('updated_at_external', '<=', $to));
    }
}
