<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InviteCode extends Model
{
    use SoftDeletes;
    protected $fillable = ['code', 'used_by', 'used_at'];
    protected $casts    = ['used_at' => 'datetime'];

    public function usedByUser()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isUsed(): bool
    {
        return $this->used_by !== null;
    }
}
