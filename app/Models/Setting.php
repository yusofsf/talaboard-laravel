<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** خواندن یک تنظیم با کش سبک؛ در صورت نبود (یا نبودِ جدول settings قبل از مهاجرت)، مقدار پیش‌فرض برمی‌گردد. */
    public static function get(string $key, $default = null)
    {
        try {
            $value = Cache::rememberForever("setting:{$key}", function () use ($key) {
                return static::query()->where('key', $key)->value('value');
            });
        } catch (\Throwable) {
            // جدول settings هنوز مهاجرت نشده یا خطای دیتابیس — نباید کل صفحه را بشکند
            return $default;
        }

        return $value ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget("setting:{$key}");
    }
}
