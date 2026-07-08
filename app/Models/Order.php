<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const ENVIRONMENT_MONGO = 'mongo';

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
        'platform_type',
        'device_platform_type',
        'area_uid',
        'area_name',
        'zone_uid',
        'zone_name',
        'division_uid',
        'division_name',
        'promo_code',
        'promo_discount_amount',
        'raw',
    ];

    protected $casts = [
        'created_at_external' => 'datetime',
        'updated_at_external' => 'datetime',
        'raw' => 'array',
        'total_amount' => 'decimal:2',
        'promo_discount_amount' => 'decimal:2',
    ];

    public function getPaymentTxnIdAttribute(): ?string
    {
        $raw = $this->raw;

        if (is_array($raw)) {
            return data_get($raw, 'payment.transactionId');
        }

        // Fallback if raw somehow stored as string
        if (is_string($raw)) {
            $arr = json_decode($raw, true);

            return is_array($arr) ? data_get($arr, 'payment.transactionId') : null;
        }

        return null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

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
            ->when($to, fn ($qq) => $qq->where('created_at_external', '<=', $to));
    }

    public function scopeUpdatedBetween($q, ?string $from, ?string $to)
    {
        return $q
            ->when($from, fn ($qq) => $qq->where('updated_at_external', '>=', $from))
            ->when($to, fn ($qq) => $qq->where('updated_at_external', '<=', $to));
    }

    public function scopeFromMongo($q)
    {
        return $q->where('environment', self::ENVIRONMENT_MONGO);
    }
}
