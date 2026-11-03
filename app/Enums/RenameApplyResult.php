<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened when a computed rename or PreDB link was applied to its release row.
 */
enum RenameApplyResult: string
{
    case Applied = 'applied';

    /** The row already held the result; nothing was written. */
    case Unchanged = 'unchanged';

    /** The row was renamed, replaced or relinked after the decision was made. */
    case StaleIdentity = 'stale_identity';

    case Missing = 'missing';

    /** The row is already linked to a different PreDB entry. */
    case PredbConflict = 'predb_conflict';

    /** The PreDB entry is gone or no longer carries the matched canonical title. */
    case PredbInvalid = 'predb_invalid';

    /** The name does not fit releases.searchname and would have been truncated. */
    case TooLong = 'too_long';

    /** The worker no longer owns the release. */
    case OwnershipLost = 'ownership_lost';

    /** A whole-season release would have been renamed to one of its episodes. */
    case SeasonPackEpisode = 'season_pack_episode';

    public function written(): bool
    {
        return $this === self::Applied;
    }
}
