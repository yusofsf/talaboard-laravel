<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'phone', 'email', 'national_id', 'password',
        'is_vip', 'is_admin', 'must_reset_password', 'legacy_password_hash',
        'membership_level', 'national_id_doc', 'identity_doc', 'verification_video',
        'membership_status', 'birth_date', 'residence_address',
    ];
    protected $hidden   = ['password', 'remember_token', 'legacy_password_hash'];

    protected function casts(): array
    {
        return [
            'password'            => 'hashed',
            'is_vip'              => 'boolean',
            'is_admin'            => 'boolean',
            'must_reset_password' => 'boolean',
            'membership_level'    => 'integer',
            'birth_date'          => 'date',
            'last_seen_at'        => 'datetime',
        ];
    }

    public function getNameAttribute($value): string
    {
        return self::plainText($value);
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = self::plainText($value);
    }

    private static function plainText($value): string
    {
        return trim(strip_tags((string) $value));
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderByDesc('created_at');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function bankCards()
    {
        return $this->hasMany(BankCard::class)->orderByDesc('created_at');
    }

    public function walletBalance(): int
    {
        return (int) $this->walletTransactions()->sum('amount');
    }

    public function unreadCount(): int
    {
        $readIds = NotificationRead::where('user_id', $this->id)->pluck('notification_id');
        return Notification::where(function ($q) {
            $q->where('user_id', $this->id)->orWhereNull('user_id');
        })->whereNotIn('id', $readIds)->count();
    }

    public function silverLedger()
    {
        return $this->hasMany(SilverLedger::class)->orderByDesc('created_at');
    }

    /** موجودی نقره‌ی فیزیکی کاربر برحسب گرم، به تفکیک عیار (999 یا 995). */
    public function silverBalance(string $purity): float
    {
        return (float) $this->silverLedger()->where('purity', $purity)->sum('grams');
    }

    public function goldLedger()
    {
        return $this->hasMany(GoldLedger::class)->orderByDesc('created_at');
    }

    /** موجودی طلای فیزیکی کاربر برحسب گرم. */
    public function goldBalance(): float
    {
        return (float) $this->goldLedger()->sum('grams');
    }

    public function isVipMember(): bool
    {
        return $this->is_vip || $this->membership_level === 2;
    }
}
