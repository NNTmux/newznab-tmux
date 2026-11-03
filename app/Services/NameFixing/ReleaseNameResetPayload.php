<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;

/**
 * Column values that undo a rename: searchname back to the usenet name, PreDB link and
 * metadata cleared, and every name-fixing and post-processing step queued again.
 */
final class ReleaseNameResetPayload
{
    /**
     * @return array<string, int|string|null>
     */
    public static function for(Release $release): array
    {
        return [
            'searchname' => $release->name,
            'isrenamed' => 0,
            'predb_id' => 0,
            'proc_pp' => 0,
            'proc_par2' => 0,
            'proc_nfo' => 0,
            'proc_files' => 0,
            'proc_uid' => 0,
            'proc_srr' => 0,
            'proc_hash16k' => 0,
            'proc_crc32' => 0,
            'videos_id' => 0,
            'tv_episodes_id' => 0,
            'imdbid' => null,
            'movieinfo_id' => null,
            'musicinfo_id' => null,
            'consoleinfo_id' => null,
            'gamesinfo_id' => 0,
            'bookinfo_id' => null,
            'anidbid' => null,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'archive_retry_at' => null,
            'pp_timeout_count' => 0,
            'jpgstatus' => 0,
            'videostatus' => 0,
            'nfostatus' => -1,
        ];
    }
}
