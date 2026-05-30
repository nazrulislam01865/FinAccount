<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => (string) config('security.headers.frame_options', 'SAMEORIGIN'),
            'Referrer-Policy' => (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
            'Permissions-Policy' => (string) config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=(), payment=()'),
        ];

        foreach ($headers as $name => $value) {
            if ($value !== '') {
                $response->headers->set($name, $value, false);
            }
        }

        if ((bool) config('security.headers.hsts_enabled', false)) {
            $response->headers->set('Strict-Transport-Security', (string) config('security.headers.hsts', 'max-age=31536000; includeSubDomains; preload'));
        }

        if ((bool) config('security.headers.csp_enabled', true)) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $policy = config('security.headers.csp', []);

        if (! is_array($policy) || $policy === []) {
            $policy = [
                'default-src' => ["'self'"],
                'base-uri' => ["'self'"],
                'object-src' => ["'none'"],
                'frame-ancestors' => ["'self'"],
                'form-action' => ["'self'"],
                'img-src' => ["'self'", 'data:', 'https:'],
                'font-src' => ["'self'", 'data:'],
                'style-src' => ["'self'", "'unsafe-inline'"],
                'script-src' => ["'self'", "'unsafe-inline'"],
                'connect-src' => ["'self'"],
            ];
        }

        return collect($policy)
            ->map(function ($values, string $directive): string {
                $values = is_array($values) ? $values : [$values];

                return trim($directive . ' ' . implode(' ', array_filter($values)));
            })
            ->filter()
            ->implode('; ');
    }
}
