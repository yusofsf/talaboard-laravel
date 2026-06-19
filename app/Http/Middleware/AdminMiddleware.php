<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || (!$user->is_admin && $user->phone !== env('ADMIN_PHONE'))) {
            abort(403, 'دسترسی محدود به ادمین');
        }
        return $next($request);
    }
}
