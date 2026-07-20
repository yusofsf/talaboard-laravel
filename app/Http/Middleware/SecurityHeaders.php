<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('csp_nonce', bin2hex(random_bytes(16)));

        $response = $next($request);

        $this->setHeaderIfMissing($response, 'X-Frame-Options', 'SAMEORIGIN');
        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=()');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Opener-Policy', 'same-origin');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Resource-Policy', 'same-origin');
        $this->setHeaderIfMissing($response, 'Origin-Agent-Cluster', '?1');
        $this->setHeaderIfMissing($response, 'X-Permitted-Cross-Domain-Policies', 'none');
        $this->setHeaderIfMissing($response, 'Content-Security-Policy', $this->contentSecurityPolicy());

        if (config('seo.force_https')) {
            $this->setHeaderIfMissing($response, 'Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    private function setHeaderIfMissing(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = (string) request()->attributes->get('csp_nonce', '');
        $scriptNonce = $nonce !== '' ? " 'nonce-{$nonce}'" : '';

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "script-src 'self'{$scriptNonce} https://s3.tradingview.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://*.tradingview.com",
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https: https://speed.cloudflare.com",
            "media-src 'self' blob:",
            "frame-src 'self' https://*.tradingview.com https://www.tgju.org",
            'upgrade-insecure-requests',
        ]);
    }
}
