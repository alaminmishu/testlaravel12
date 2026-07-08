<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SyncState extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'datetime',
        ];
    }

    public static function getWatermark(string $key): ?Carbon
    {
        return static::query()->where('key', $key)->first()?->value;
    }

    public static function setWatermark(string $key, DateTimeInterface $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
