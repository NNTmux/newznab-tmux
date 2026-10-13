<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A TV metadata provider failed transiently (rate limit, 5xx, timeout, connection error).
 *
 * A 404 or an empty result is never transient; providers return false/null for those.
 */
class TvProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public static function isTransientStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * Seconds from a Retry-After header (delta-seconds or HTTP-date), or null when absent.
     */
    public static function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) $header);
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }
}
