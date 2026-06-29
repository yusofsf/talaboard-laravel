<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    // متدهای تغییردهنده — فقط اقدام‌های واقعی کاربر/ادمین ثبت می‌شوند، نه بازکردن صفحه‌ها.
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // کلیدهای حساس که حتی نام‌شان هم در لاگ نباید با مقدار بیاید (اینجا فقط نام کلیدها ثبت می‌شود، نه مقدار).
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** بعد از ارسال پاسخ اجرا می‌شود تا کد وضعیت هم در دسترس باشد. */
    public function terminate(Request $request, Response $response): void
    {
        if (!in_array($request->method(), self::MUTATING, true)) {
            return;
        }

        try {
            $user = $request->user();

            Log::channel('access')->info($request->method() . ' ' . $request->path(), [
                'route'    => optional($request->route())->getName(),
                'status'   => $response->getStatusCode(),
                'user_id'  => $user?->id,
                'phone'    => $user?->phone,
                'is_admin' => (bool) ($user?->is_admin),
                'ip'       => $request->ip(),
                'fields'   => array_keys($request->except(['password', 'password_confirmation', 'otp', 'code', 'token', '_token', '_method'])),
            ]);
        } catch (\Throwable) {
            // ثبت‌نشدن لاگ هرگز نباید جریان اصلی را بشکند
        }
    }
}
