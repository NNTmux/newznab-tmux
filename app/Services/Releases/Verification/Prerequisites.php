<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

final class Prerequisites
{
    public function check(bool $required, string $par2Binary): void
    {
        if (! $required) {
            return;
        }
        $available = str_contains($par2Binary, '/') || str_contains($par2Binary, '\\')
            ? is_file($par2Binary) && is_executable($par2Binary)
            : (new ExecutableFinder)->find($par2Binary) !== null;
        if (! $available) {
            throw new RuntimeException('Release verification requires an executable PAR2 binary. Install par2 or configure RELEASE_VERIFICATION_PAR2_BINARY.');
        }
    }
}
