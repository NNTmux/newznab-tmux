<?php

namespace App\Services;

use App\Enums\BlacklistConstants;
use App\Models\BinaryBlacklist;
use Illuminate\Support\Facades\DB;

class BlacklistService
{
    /**
     * Cache of lists per group name.
     */
    private array $blackList = [];

    private array $whiteList = [];

    private array $listsFound = [];

    /**
     * Track blacklist IDs to update last_activity for.
     */
    private array $idsToUpdate = [];

    /**
     * Check if an article (OVER header) is blacklisted for the given group.
     */
    public function isBlackListed(array $msg, string $groupName): bool
    {
        if (! isset($this->listsFound[$groupName])) {
            $this->retrieveLists($groupName);
        }
        if (! $this->listsFound[$groupName]) {
            return false;
        }

        $blackListed = false;
        $field = [
            BlacklistConstants::BLACKLIST_FIELD_SUBJECT => $msg['Subject'] ?? '',
            BlacklistConstants::BLACKLIST_FIELD_FROM => $msg['From'] ?? '',
            BlacklistConstants::BLACKLIST_FIELD_MESSAGEID => $msg['Message-ID'] ?? '',
        ];

        // Whitelist first: if any whitelist matches, allow; otherwise treat as blacklisted.
        if ($this->whiteList[$groupName]) {
            $blackListed = true;
            foreach ($this->whiteList[$groupName] as $whiteList) {
                if (@preg_match('/'.$whiteList->regex.'/i', $field[$whiteList->msgcol])) {
                    $blackListed = false;
                    $this->idsToUpdate[$whiteList->id] = $whiteList->id;
                    break;
                }
            }
        }

        if (! $blackListed && $this->blackList[$groupName]) {
            foreach ($this->blackList[$groupName] as $blackList) {
                if (@preg_match('/'.$blackList->regex.'/i', $field[$blackList->msgcol])) {
                    $blackListed = true;
                    $this->idsToUpdate[$blackList->id] = $blackList->id;
                    break;
                }
            }
        }

        return $blackListed;
    }

    /**
     * Get and reset collected blacklist IDs that matched during checks.
     */
    public function getAndClearIdsToUpdate(): array
    {
        $ids = array_values($this->idsToUpdate);
        $this->idsToUpdate = [];

        return $ids;
    }

    /**
     * Update last_activity timestamp for given blacklist IDs.
     */
    public function updateBlacklistUsage(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        BinaryBlacklist::query()->whereIn('id', $ids)->update(['last_activity' => now()]);
    }

    /**
     * Query blacklists from DB.
     */
    public function getBlacklist(bool $activeOnly = true, int|string $opType = -1, string $groupName = '', bool $groupRegex = false): array
    {
        $opTypeSql = match ($opType) {
            BlacklistConstants::OPTYPE_BLACKLIST => 'AND bb.optype = '.BlacklistConstants::OPTYPE_BLACKLIST,
            BlacklistConstants::OPTYPE_WHITELIST => 'AND bb.optype = '.BlacklistConstants::OPTYPE_WHITELIST,
            default => '',
        };

        $joinOperator = $groupRegex ? 'REGEXP' : '=';
        $activeSql = $activeOnly ? 'AND bb.status = 1' : '';
        $groupSql = $groupName ? ('AND g.name REGEXP '.escapeString($groupName)) : '';

        $sql = "
                SELECT
                    bb.id, bb.optype, bb.status, bb.description,
                    bb.groupname AS groupname, bb.regex, g.id AS group_id, bb.msgcol,
                    bb.last_activity as last_activity
                FROM binaryblacklist bb
                LEFT OUTER JOIN usenet_groups g ON g.name $joinOperator bb.groupname
                WHERE 1=1 $activeSql $opTypeSql $groupSql
                ORDER BY coalesce(groupname,'zzz')";

        return DB::select($sql);
    }

    public function getBlacklistByID(int $id)
    {
        return BinaryBlacklist::query()->where('id', $id)->first();
    }

    public function deleteBlacklist(int $id): void
    {
        BinaryBlacklist::query()->where('id', $id)->delete();
    }

    public function updateBlacklist(array $blacklistArray): void
    {
        BinaryBlacklist::query()->where('id', $blacklistArray['id'])->update(
            [
                'groupname' => $blacklistArray['groupname'] === '' ? 'null' : preg_replace('/a\.b\./i', 'alt.binaries.', $blacklistArray['groupname']),
                'regex' => $blacklistArray['regex'],
                'status' => $blacklistArray['status'],
                'description' => $blacklistArray['description'],
                'optype' => $blacklistArray['optype'],
                'msgcol' => $blacklistArray['msgcol'],
            ]
        );
    }

    public function addBlacklist(array $blacklistArray): void
    {
        BinaryBlacklist::query()->insert(
            [
                'groupname' => $blacklistArray['groupname'] === '' ? 'null' : preg_replace('/a\.b\./i', 'alt.binaries.', $blacklistArray['groupname']),
                'regex' => $blacklistArray['regex'],
                'status' => $blacklistArray['status'],
                'description' => $blacklistArray['description'],
                'optype' => $blacklistArray['optype'],
                'msgcol' => $blacklistArray['msgcol'],
            ]
        );
    }

    private function retrieveLists(string $groupName): void
    {
        if (! isset($this->blackList[$groupName])) {
            $this->blackList[$groupName] = $this->getBlacklist(true, BlacklistConstants::OPTYPE_BLACKLIST, $groupName, true);
        }
        if (! isset($this->whiteList[$groupName])) {
            $this->whiteList[$groupName] = $this->getBlacklist(true, BlacklistConstants::OPTYPE_WHITELIST, $groupName, true);
        }
        $this->listsFound[$groupName] = ($this->blackList[$groupName] || $this->whiteList[$groupName]);
    }
}
