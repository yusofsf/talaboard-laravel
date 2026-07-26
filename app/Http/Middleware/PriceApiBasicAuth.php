<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PriceApiBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $storedUsername = Setting::get('price_api_username');
        $storedSecretHash = Setting::get('price_api_secret_hash');

        if ($storedUsername !== null && $storedSecretHash !== null) {
            $valid = hash_equals((string) $storedUsername, (string) $request->getUser())
                && Hash::check((string) $request->getPassword(), (string) $storedSecretHash);
        } else {
            $username = (string) config('services.price_api.username');
            $secret = (string) config('services.price_api.secret');
            $valid = $username !== ''
                && $secret !== ''
                && hash_equals($username, (string) $request->getUser())
                && hash_equals($secret, (string) $request->getPassword());
        }

        if (! $valid) {
            return response()->json([
                'message' => 'نام کاربری یا سکرت API نامعتبر است.',
            ], 401, [
                'WWW-Authenticate' => 'Basic realm="Price API", charset="UTF-8"',
            ]);
        }

        return $next($request);
    }
}
