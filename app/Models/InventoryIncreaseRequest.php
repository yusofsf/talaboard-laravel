<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryIncreaseRequest extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'metal', 'purity', 'grams', 'note', 'receipt_path', 'source', 'status', 'admin_note'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
