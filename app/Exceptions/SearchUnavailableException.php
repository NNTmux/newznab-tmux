<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An API search failed in the search engine and there is no result to fall
 * back on. Rendered as a retryable 503 in the calling API version's format.
 */
final class SearchUnavailableException extends RuntimeException
{
    public const int RETRY_AFTER_SECONDS = 30;

    public function __construct(public readonly string $apiVersion)
    {
        parent::__construct('Search is temporarily unavailable, retry shortly');
    }

    public function render(): Response
    {
        $response = $this->apiVersion === 'v2'
            ? apiJsonError(900, $this->getMessage())
            : showApiError(900, $this->getMessage());

        $response->setStatusCode(503);
        $response->headers->set('Retry-After', (string) self::RETRY_AFTER_SECONDS);

        return $response;
    }
}
