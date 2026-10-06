<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Services\BlacklistService;

/**
 * Parses and filters raw NNTP headers.
 */
final class HeaderParser
{
    private BlacklistService $blacklistService;

    private int $notYEnc = 0;

    private int $blacklisted = 0;

    public function __construct(?BlacklistService $blacklistService = null)
    {
        $this->blacklistService = $blacklistService ?? new BlacklistService;
    }

    /**
     * Reset counters for a new batch.
     */
    public function reset(): void
    {
        $this->notYEnc = 0;
        $this->blacklisted = 0;
    }

    /**
     * Parse and filter raw headers from NNTP.
     *
     * @param  array<int, array<string, mixed>>  $headers  Raw headers from NNTP
     * @param  string  $groupName  The newsgroup name
     * @param  bool  $partRepair  Whether this is a part repair scan
     * @param  array<int, mixed>|null  $missingParts  Missing part numbers if part repair
     * @return array<string, mixed> Filtered and parsed headers with article info
     */
    public function parse(
        array $headers,
        string $groupName,
        bool $partRepair = false,
        ?array $missingParts = null
    ): array {
        $parsed = [];
        $headersRepaired = [];
        $receivedNumbers = [];
        $missingPartSet = $missingParts === null
            ? null
            : (array_is_list($missingParts)
                ? array_fill_keys(array_map('intval', $missingParts), true)
                : $missingParts);

        foreach ($headers as $header) {
            // Check if we got the article
            if (! isset($header['Number'])) {
                continue;
            }

            $receivedNumbers[] = $header['Number'];

            // For part repair, only process missing parts
            if ($partRepair && $missingPartSet !== null) {
                if (! isset($missingPartSet[(int) $header['Number']])) {
                    continue;
                }
                $headersRepaired[] = $header['Number'];
            }

            // Parse subject to get base name and part/total like "(12/45)"
            if (! preg_match('/^\s*(?!Usenet Index Post)(.+?)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $matches)) {
                $this->notYEnc++;

                continue;
            }

            // Normalize to include yEnc if missing
            if (stripos($header['Subject'], 'yEnc') === false) {
                $matches[1] .= ' yEnc';
            }

            $header['matches'] = $matches;

            // Filter subject based on black/white list
            if ($this->blacklistService->isBlackListed($header, $groupName)) {
                $this->blacklisted++;

                continue;
            }

            // Ensure Bytes is set
            if (empty($header['Bytes'])) {
                $header['Bytes'] = $header[':bytes'] ?? 0;
            }

            $parsed[] = $header;
        }

        return [
            'headers' => $parsed,
            'repaired' => $headersRepaired,
            'received' => $receivedNumbers,
            'notYEnc' => $this->notYEnc,
            'blacklisted' => $this->blacklisted,
        ];
    }

    /**
     * Update blacklist last_activity for matched rules.
     */
    public function flushBlacklistUpdates(): void
    {
        $ids = $this->blacklistService->getAndClearIdsToUpdate();
        if (! empty($ids)) {
            $this->blacklistService->updateBlacklistUsage($ids); // @phpstan-ignore argument.type
        }
    }

    /**
     * Get count of non-yEnc headers filtered.
     */
    public function getNotYEncCount(): int
    {
        return $this->notYEnc;
    }

    /**
     * Get count of blacklisted headers.
     */
    public function getBlacklistedCount(): int
    {
        return $this->blacklisted;
    }

    /**
     * Extract highest and lowest article info from headers.
     *
     * Only numbers inside the requested range count: a malformed overview line can
     * carry a bogus number, and saving it as last_record makes the group re-scan
     * from article 0.
     *
     * @param  array<int, array<string, mixed>>  $headers
     * @return array<string, mixed>
     */
    public function getArticleRange(array $headers, ?int $first = null, ?int $last = null): array
    {
        $result = [];
        $low = $high = null;

        foreach ($headers as $header) {
            if (! isset($header['Number']) || ! is_numeric($header['Number'])) {
                continue;
            }
            $number = (int) $header['Number'];
            if (($first !== null && $number < $first) || ($last !== null && $number > $last)) {
                continue;
            }
            if ($low === null || $number < (int) $low['Number']) {
                $low = $header;
            }
            if ($high === null || $number > (int) $high['Number']) {
                $high = $header;
            }
        }

        if ($low !== null) {
            $result['firstArticleNumber'] = $low['Number'];
            $result['firstArticleDate'] = $low['Date'] ?? null;
            $result['lastArticleNumber'] = $high['Number'];
            $result['lastArticleDate'] = $high['Date'] ?? null;
        }

        return $result;
    }
}
