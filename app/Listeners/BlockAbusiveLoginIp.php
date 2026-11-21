<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Security\LoginAbuseDetector;
use Spatie\SuspiciousLogin\Events\LoginAttemptRecordedEvent;

/**
 * Checks the address behind every failed sign-in to an account that does not exist, right
 * after the attempt is recorded, so an address that crosses the threshold is refused from
 * its next request on.
 */
class BlockAbusiveLoginIp
{
    public function __construct(private readonly LoginAbuseDetector $detector) {}

    /**
     * Handle the event.
     */
    public function handle(LoginAttemptRecordedEvent $event): void
    {
        $attempt = $event->loginAttempt;

        if (! config('nntmux.ip_blocking.auto_block')
            || $attempt->successful
            || $attempt->authenticatable_id !== null
            || ! is_string($attempt->ip_address)
            || $attempt->ip_address === ''
        ) {
            return;
        }

        try {
            $this->detector->evaluate($attempt->ip_address, $attempt->device_fingerprint);
        } catch (\Throwable $throwable) {
            // Detection must never turn a failed sign-in into an error page.
            report($throwable);
        }
    }
}
