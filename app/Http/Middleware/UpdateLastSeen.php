<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        // برای کاهش تعداد نوشتن روی دیتابیس، فقط هر ۶۰ ثانیه یک‌بار به‌روزرسانی می‌شود.
        if ($user && (!$user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(60)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
        return $next($request);
    }
}
