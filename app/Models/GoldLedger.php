<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldLedger extends Model
{
    protected $table = 'gold_ledger';

    protected $fillable = ['user_id', 'grams', 'type', 'reference_type', 'reference_id', 'description'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
