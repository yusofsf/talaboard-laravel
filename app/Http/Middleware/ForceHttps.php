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

        if (config('seo.force_https') && ! $hasCanonicalOrigin) {
            $status = $request->isMethodSafe() ? 301 : 308;

            return redirect()->to($canonicalUrl.$request->getRequestUri(), $status);
        }

        return $next($request);
    }
}
