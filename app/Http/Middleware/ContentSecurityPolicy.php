<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tell Vite to add the CSP nonce to generated script/style tags
        $nonce = function_exists('csp_nonce') ? csp_nonce() : base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

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

        // Reuse the nonce generated above (shared with Blade via csp_nonce())

        // Build CSP directives for non-Turnstile pages
        // 'strict-dynamic' propagates trust from nonce-validated scripts to
        // dynamically loaded scripts (e.g. TinyMCE loaded via createElement).
        // Host-based allowlists are kept as fallback for older browsers.
        $directives = [
            "default-src 'self'",
            // 'unsafe-eval' is required because @alpinejs/csp bundles dead-code from the
            // standard Alpine evaluator (new Function) that cannot be tree-shaken, and
            // TinyMCE (loaded from CDN on admin content pages) also relies on eval.
            // 'unsafe-inline' has been removed in favor of nonce-based validation.
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' 'strict-dynamic' https://challenges.cloudflare.com https://cdn.tiny.cloud https://cdn.jsdelivr.net/ https://static.cloudflareinsights.com/ https://cdnjs.cloudflare.com/ https://unpkg.com/ https://cdn.tailwindcss.com/ https://code.jquery.com https://apis.google.com https://www.google.com https://www.gstatic.com https://ajax.cloudflare.com blob:",
            "script-src-elem 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com https://cdn.tiny.cloud https://cdn.jsdelivr.net/ https://static.cloudflareinsights.com/ https://cdnjs.cloudflare.com/ https://unpkg.com/ https://cdn.tailwindcss.com/ https://code.jquery.com https://apis.google.com https://www.google.com https://www.gstatic.com https://ajax.cloudflare.com",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net/ https://cdnjs.cloudflare.com/ https://cdn.tiny.cloud",
            "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net/ https://cdnjs.cloudflare.com/ https://cdn.tiny.cloud",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com/ https://cdn.tiny.cloud data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://www.google.com https://cdn.tiny.cloud https://sp.tinymce.com",
            "frame-src 'self' https://www.google.com https://www.gstatic.com https://challenges.cloudflare.com https://cdn.tiny.cloud data: blob:",
            "child-src 'self' https://www.google.com https://challenges.cloudflare.com https://cdn.tiny.cloud blob:",
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
