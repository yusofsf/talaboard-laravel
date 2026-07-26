<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use SoftDeletes;

    public const ABILITIES = [
        'prices.read' => 'دریافت قیمت لحظه‌ای',
        'trade-room.read' => 'مشاهده سفارش‌های اتاق معاملاتی',
        'trade-room.create' => 'ثبت سفارش در اتاق معاملاتی',
        'shop-order.create' => 'ثبت سفارش فروشگاه',
        'users.read' => 'دسترسی به نام و شماره موبایلِ کاربرِ توکن',
    ];

    protected $fillable = ['user_id', 'name', 'token_hash', 'abilities', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected $casts = ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true) && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public static function issue(User $user, string $name, array $abilities, ?string $expiresAt = null): array
    {
        $plain = 'tlb_'.Str::random(48);
        $token = static::create([
            'user_id' => $user->id, 'name' => $name, 'token_hash' => hash('sha256', $plain),
            'abilities' => array_values(array_intersect($abilities, array_keys(self::ABILITIES))),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }
}
