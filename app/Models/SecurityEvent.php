<?php

namespace App\Models;

use App\Helpers\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event_type', 'severity', 'route_name', 'path', 'method',
        'ip', 'user_agent', 'payload', 'matched_fields', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'matched_fields' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function recordSuspiciousInput(Request $request, string $eventType, array $matchedFields): void
    {
        try {
            $event = static::create([
                'user_id' => $request->user()?->id,
                'event_type' => $eventType,
                'severity' => 'high',
                'route_name' => optional($request->route())->getName(),
                'path' => '/' . ltrim($request->path(), '/'),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'payload' => self::safePayload($request->all()),
                'matched_fields' => $matchedFields,
                'created_at' => now(),
            ]);

            self::notifyAdmins($event);
        } catch (\Throwable $e) {
            Log::channel('security')->warning('Failed to persist security event', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'matched_fields' => $matchedFields,
            ]);
        }

        try {
            Log::channel('security')->warning('Suspicious input detected', [
                'event_type' => $eventType,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => optional($request->route())->getName(),
                'matched_fields' => $matchedFields,
            ]);
        } catch (\Throwable) {
            //
        }
    }

    private static function notifyAdmins(self $event): void
    {
        try {
            $userLabel = $event->user
                ? "{$event->user->name} ({$event->user->phone})"
                : 'کاربر مهمان';

            User::where('is_admin', true)->pluck('id')->each(fn ($adminId) => Notification::create([
                'user_id' => $adminId,
                'title' => 'هشدار امنیتی: ورودی مشکوک',
                'body' => "{$userLabel} در مسیر {$event->path} ورودی مشکوک ارسال کرد. IP: {$event->ip}. تاریخ: " . Jalali::now(),
                'type' => 'system',
            ]));
        } catch (\Throwable) {
            //
        }
    }

    private static function safePayload(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $safe[$key] = '[masked]';
                continue;
            }

            $safe[$key] = self::truncateValue($value);
        }

        return $safe;
    }

    private static function truncateValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::truncateValue($item), array_slice($value, 0, 20, true));
        }

        if (is_scalar($value) || $value === null) {
            return mb_substr((string) $value, 0, 500);
        }

        return '[unlogged]';
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/password|pass|token|_token|otp|code|secret|api[_-]?key|authorization/i', $key);
    }
}
