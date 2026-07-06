<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectSuspiciousInput
{
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'old_password', 'new_password',
        'otp', 'code', 'token', '_token', 'secret',
    ];

    private const PATTERNS = [
        'xss_attempt' => [
            '/<\s*script\b/i',
            '/<\s*\/\s*script\s*>/i',
            '/\bon[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/data\s*:\s*text\/html/i',
            '/vbscript\s*:/i',
            '/<\s*(iframe|object|embed|svg|img|body|link|meta)\b/i',
        ],
        'sql_injection_attempt' => [
            '/\bunion\s+select\b/i',
            '/\binformation_schema\b/i',
            '/\bsleep\s*\(/i',
            '/\bbenchmark\s*\(/i',
            '/;\s*drop\s+table\b/i',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::MUTATING, true)) {
            $matches = $this->matches($request->all());

            if ($matches !== []) {
                $eventType = str_starts_with($matches[0]['type'], 'sql_') ? 'sql_injection_attempt' : 'xss_attempt';
                SecurityEvent::recordSuspiciousInput($request, $eventType, $matches);

                return back()
                    ->withErrors([$matches[0]['field'] => 'ورودی مشکوک شناسایی شد و برای بررسی امنیتی ثبت شد.'])
                    ->withInput($request->except(self::SENSITIVE_KEYS));
            }
        }

        return $next($request);
    }

    private function matches(array $payload, string $prefix = ''): array
    {
        $matches = [];

        foreach ($payload as $key => $value) {
            $field = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $matches = array_merge($matches, $this->matches($value, $field));
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $text = (string) $value;
            foreach (self::PATTERNS as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $text)) {
                        $matches[] = [
                            'field' => $field,
                            'type' => $type,
                            'sample' => mb_substr($text, 0, 160),
                        ];
                        break 2;
                    }
                }
            }
        }

        return $matches;
    }

    private function isSensitiveKey(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true)
            || (bool) preg_match('/password|pass|token|otp|code|secret|api[_-]?key|authorization/i', $key);
    }
}
