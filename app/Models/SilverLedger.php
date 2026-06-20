<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SilverLedger extends Model
{
    protected $table = 'silver_ledger';

    protected $fillable = ['user_id', 'purity', 'grams', 'type', 'reference_type', 'reference_id', 'description'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
