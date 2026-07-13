<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        $canonicalUrl = rtrim(config('seo.url'), '/');
        $canonicalScheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        $hasCanonicalOrigin = $request->getScheme() === $canonicalScheme
            && strtolower($request->getHost()) === strtolower((string) $canonicalHost);

        if (config('seo.force_https') && ! $hasCanonicalOrigin && $request->isMethodSafe()) {
            return redirect()->to($canonicalUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
