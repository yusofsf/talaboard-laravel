<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::with('user')->where('token_hash', hash('sha256', $plain))->first() : null;
        if (! $token || ! $token->user || ! $token->allows($ability)) {
            return response()->json(['message' => 'توکن معتبر نیست یا مجوز این عملیات را ندارد.'], 403);
        }
        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('apiToken', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
