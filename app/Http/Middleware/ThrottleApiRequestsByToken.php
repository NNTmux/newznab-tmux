<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ThrottleApiRequestsByToken
{
    private const int DEFAULT_RATE_LIMIT = 60;

    private const int DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            return $next($request);
        }

        $maxAttempts = max(1, (int) ($user->rate_limit ?: self::DEFAULT_RATE_LIMIT));
        $rateLimitKey = $this->rateLimitKey($user->id);

        if ($this->limiter->tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->buildTooManyRequestsResponse($rateLimitKey, $maxAttempts);
        }

        $this->limiter->hit($rateLimitKey, self::DECAY_SECONDS);

        $response = $next($request);
        $remainingAttempts = max(0, $maxAttempts - $this->limiter->attempts($rateLimitKey));

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remainingAttempts);

        return $response;
    }

    private function resolveUser(Request $request): ?User
    {
        $apiToken = $request->input('api_token');

        if (! is_string($apiToken) || $apiToken === '') {
            return null;
        }

        return Cache::remember('api_rate_limit_user:'.md5($apiToken), 300, static function () use ($apiToken) {
            return User::verifiedApiTokenQuery($apiToken)
                ->select(['id', 'api_token', 'rate_limit'])
                ->first();
        });
    }

    private function rateLimitKey(int $userId): string
    {
        return 'api-rate-limit:user:'.$userId;
    }

    private function buildTooManyRequestsResponse(string $rateLimitKey, int $maxAttempts): JsonResponse
    {
        $retryAfter = max(1, $this->limiter->availableIn($rateLimitKey));

        return response()->json([
            'error' => 'API rate limit exceeded.',
            'retry_after' => $retryAfter,
        ], 429, [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $maxAttempts,
            'X-RateLimit-Remaining' => '0',
        ]);
    }
}
