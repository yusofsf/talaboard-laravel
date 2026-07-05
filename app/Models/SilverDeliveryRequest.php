<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SilverDeliveryRequest extends Model
{
    protected $fillable = ['user_id', 'metal', 'purity', 'grams', 'recipient_name', 'phone', 'address', 'delivery_method', 'status', 'admin_note'];

    protected $casts = ['grams' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
