<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'category', 'description', 'ip', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ثبت یک رویداد در گزارش فعالیت. هرگز نباید جریان اصلی را بشکند،
     * پس هر خطایی در ثبت لاگ بی‌صدا نادیده گرفته می‌شود.
     */
    public static function record(string $action, string $category, string $description, ?int $userId = null): void
    {
        $ip = Request::ip();

        try {
            static::create([
                'user_id'     => $userId,
                'action'      => $action,
                'category'    => $category,
                'description' => $description,
                'ip'          => $ip,
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
            // نادیده گرفتن — ثبت‌نشدن لاگ در دیتابیس نباید عملیات اصلی را متوقف کند
        }

        // علاوه بر دیتابیس، در فایل لاگ روی سرور هم نوشته می‌شود (storage/logs/activity-*.log)
        try {
            Log::channel('activity')->info($description, [
                'action'   => $action,
                'category' => $category,
                'user_id'  => $userId,
                'ip'       => $ip,
            ]);
        } catch (\Throwable) {
            // نادیده گرفتن
        }
    }
}
