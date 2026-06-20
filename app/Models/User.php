<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'phone', 'email', 'national_id', 'password',
        'is_vip', 'is_admin', 'must_reset_password', 'legacy_password_hash',
        'membership_level', 'national_id_doc', 'identity_doc', 'verification_video',
        'membership_status',
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
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderByDesc('created_at');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
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
}
