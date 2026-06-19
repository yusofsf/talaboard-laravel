<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    public $timestamps = false;
    protected $fillable = ['notification_id', 'user_id', 'read_at'];
}
