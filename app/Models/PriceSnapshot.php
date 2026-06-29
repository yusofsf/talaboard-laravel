<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceSnapshot extends Model
{
    protected $fillable = ['payload'];

    protected $casts = ['payload' => 'array'];

    /** آخرین عکس فوری قیمت‌ها که فرمان prices:snapshot ذخیره کرده است. */
    public static function latestPayload(): ?array
    {
        $row = static::query()->latest('id')->first();
        return $row?->payload;
    }
}
