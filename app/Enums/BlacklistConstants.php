<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Constants for blacklist/whitelist operations.
 * Migrated from Blacklight\Binaries.
 */
final class BlacklistConstants
{
    // Operation type constants
    public const OPTYPE_BLACKLIST = 1;

    public const OPTYPE_WHITELIST = 2;

    // Blacklist status constants
    public const BLACKLIST_ENABLED = 1;

    // Blacklist field constants
    public const BLACKLIST_FIELD_SUBJECT = 1;

    public const BLACKLIST_FIELD_FROM = 2;

    public const BLACKLIST_FIELD_MESSAGEID = 3;
}
