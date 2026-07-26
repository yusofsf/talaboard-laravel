<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use SoftDeletes;

    /** Scopes that require a token to be linked to a site user. */
    public const USER_ABILITIES = [
        'trades:create',
        'wallet:read',
        'profile:read',
        'alerts:manage',
    ];

    /**
     * Publicly issuable API scopes. Keep endpoint middleware in sync with this
     * map so an issued scope always grants a clear, limited capability.
     */
    public const ABILITIES = [
        'prices:read' => 'دریافت قیمت‌های لحظه‌ای',
        'trades:read' => 'مشاهده سفارش‌های اتاق معاملاتی',
        'trades:create' => 'ثبت سفارش اتاق معاملاتی و فروشگاه',
        'wallet:read' => 'مشاهده موجودی و گردش کیف پول',
        'profile:read' => 'مشاهده اطلاعات پروفایلِ صاحب توکن',
        'alerts:manage' => 'مشاهده و خواندن اعلان‌های صاحب توکن',
    ];

    protected $fillable = ['user_id', 'client_name', 'name', 'token_hash', 'abilities', 'last_used_at', 'expires_at'];

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

    public static function issue(?User $user, string $clientName, string $name, array $abilities, ?string $expiresAt = null): array
    {
        $plain = 'tlb_'.Str::random(48);
        $token = static::create([
            'user_id' => $user?->id,
            'client_name' => $user?->name ?? $clientName,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'abilities' => array_values(array_intersect($abilities, array_keys(self::ABILITIES))),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }
}
