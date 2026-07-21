<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            // A redirect after a state-changing HTTP request does not protect its
            // body or cookies. Reject it instead, so clients retry over HTTPS.
            if (! $request->isMethodSafe()) {
                abort(Response::HTTP_UPGRADE_REQUIRED, 'HTTPS is required.');
            }

            return redirect()->to($canonicalUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
