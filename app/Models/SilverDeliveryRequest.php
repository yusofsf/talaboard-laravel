<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SilverDeliveryRequest extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'metal', 'purity', 'grams', 'recipient_name', 'phone', 'address', 'postal_code', 'delivery_method', 'source', 'status', 'admin_note'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
