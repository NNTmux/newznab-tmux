<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if Turnstile is enabled
        $turnstileEnabled = config('captcha.provider') === 'turnstile'
            && config('captcha.turnstile.enabled') === true;

        // If Turnstile is enabled, skip CSP to avoid sandbox iframe issues
        // Turnstile creates sandboxed iframes that conflict with strict CSP
        if ($turnstileEnabled) {
            // Set minimal security headers only
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            return $response;
        }

        // Generate nonce for inline scripts
        $nonce = function_exists('csp_nonce') ? csp_nonce() : base64_encode(random_bytes(16));

        // Build CSP directives for non-Turnstile pages
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://unpkg.com blob:",
            "script-src-elem 'self' 'nonce-{$nonce}' 'unsafe-inline' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://unpkg.com",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://www.google.com",
            "frame-src 'self' https://www.google.com https://www.gstatic.com data: blob:",
            "child-src 'self' https://www.google.com blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        $csp = implode('; ', $directives);

        $response->headers->set('Content-Security-Policy', $csp);

        // Also set X-Frame-Options for older browsers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Set X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Set Referrer-Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
