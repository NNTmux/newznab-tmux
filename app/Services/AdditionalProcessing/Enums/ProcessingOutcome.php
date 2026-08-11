<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

enum ProcessingOutcome: string
{
    case Completed = 'completed';
    case NoUsefulArtifacts = 'no-useful-artifacts';
    case Passworded = 'passworded';
    case GroupUnavailable = 'group-unavailable';
    case TemporaryWorkspaceUnavailable = 'temporary-workspace-unavailable';
    case TimedOut = 'timed-out';
    case DeletedAfterTimeout = 'deleted-after-timeout';
    case DeletedBrokenNzb = 'deleted-broken-nzb';
    case NotFound = 'not-found';
    case Failed = 'failed';

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::Completed, self::NoUsefulArtifacts, self::Passworded => true,
            default => false,
        };
    }

    public function isDeleted(): bool
    {
        return match ($this) {
            self::DeletedAfterTimeout, self::DeletedBrokenNzb => true,
            default => false,
        };
    }
}
