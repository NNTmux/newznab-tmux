<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class BookProviderException extends RuntimeException
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
}
