<?php

declare(strict_types=1);

namespace Tests\Support {
    final class MarkerIoFailures
    {
        public static bool $shortWrite = false;

        public static bool $renameFails = false;

        public static bool $directorySyncFails = false;

        public static int $directorySyncCalls = 0;

        public static ?int $directorySyncFailFromCall = null;

        public static bool $checkpointSyncFails = false;

        public static ?string $syncFailureUri = null;
    }
}

namespace App\Services\Tmux {
    use Tests\Support\MarkerIoFailures;

    /** @param resource $stream */
    function fwrite($stream, string $data): int|false
    {
        if (MarkerIoFailures::$shortWrite) {
            return \fwrite($stream, substr($data, 0, 6));
        }

        return \fwrite($stream, $data);
    }

    function rename(string $from, string $to): bool
    {
        return ! MarkerIoFailures::$renameFails && \rename($from, $to);
    }

    /**
     * @param  resource  $stream
     *
     * @phpstan-impure
     */
    function fsync($stream): bool
    {
        $metadata = stream_get_meta_data($stream);
        if (MarkerIoFailures::$syncFailureUri !== null && ($metadata['uri'] ?? '') === MarkerIoFailures::$syncFailureUri) {
            return false;
        }
        if (MarkerIoFailures::$checkpointSyncFails && str_ends_with($metadata['uri'] ?? '', '.recovery')) {
            return false;
        }
        $stat = fstat($stream);
        if ($stat !== false && ($stat['mode'] & 0170000) === 0040000) {
            MarkerIoFailures::$directorySyncCalls++;
            $failFrom = MarkerIoFailures::$directorySyncFailFromCall;
            if (MarkerIoFailures::$directorySyncFails || ($failFrom !== null && MarkerIoFailures::$directorySyncCalls >= $failFrom)) {
                return false;
            }
        }

        return \fsync($stream);
    }
}
