<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryIncreaseRequest extends Model
{
    protected $fillable = ['user_id', 'metal', 'purity', 'grams', 'note', 'status', 'admin_note'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
