<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Classifies SQL failures and renders concise log messages for them.
 *
 * QueryException messages embed the full statement with every binding, which
 * turns bulk-ingest lock errors into multi-kilobyte log lines. describe()
 * strips that payload; isTransientLock() recognizes retryable lock conflicts.
 */
final class SqlError
{
    /**
     * InnoDB driver codes: 1020 ER_CHECKREAD (record changed since last read),
     * 1205 lock wait timeout, 1213 deadlock.
     */
    private const array TRANSIENT_DRIVER_CODES = [1020, 1205, 1213];

    private const int MAX_MESSAGE_LENGTH = 500;

    public static function isTransientLock(\Throwable $exception): bool
    {
        if ($exception instanceof QueryException) {
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            if ($exception->getCode() === '40001' || \in_array($driverCode, self::TRANSIENT_DRIVER_CODES, true)) {
                return true;
            }
        }

        return str_contains($exception->getMessage(), 'Deadlock found')
            || str_contains($exception->getMessage(), 'Lock wait timeout exceeded')
            || str_contains($exception->getMessage(), 'Record has changed since last read')
            || str_contains($exception->getMessage(), 'database is locked');
    }

    /**
     * Concise single-line description without the "(Connection: …, SQL: …)"
     * payload Laravel appends to QueryException messages.
     */
    public static function describe(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $tail = strpos($message, ' (Connection:');
        if ($tail !== false) {
            $message = substr($message, 0, $tail);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH).'…';
        }

        return $message;
    }

    /**
     * Log a failed statement: warning for transient lock conflicts (the caller
     * retries the chunk), error otherwise. Always without the SQL payload.
     */
    public static function logFailure(string $context, \Throwable $exception): void
    {
        $message = $context.': '.self::describe($exception);
        if (self::isTransientLock($exception)) {
            Log::warning($message.' (transient, chunk will be retried)');
        } else {
            Log::error($message);
        }
    }
}
