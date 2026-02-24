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
     * @param  array<string, mixed>  $headers  Raw headers from NNTP
     * @param  string  $groupName  The newsgroup name
     * @param  bool  $partRepair  Whether this is a part repair scan
     * @param  array<string, mixed>|null  $missingParts  Missing part numbers if part repair
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

        foreach ($headers as $header) {
            // Check if we got the article
            if (! isset($header['Number'])) {
                continue;
            }

            // For part repair, only process missing parts
            if ($partRepair && $missingParts !== null) {
                if (! \in_array($header['Number'], $missingParts, true)) {
                    continue;
                }
                $headersRepaired[] = $header['Number'];
            }

            // Parse subject to get base name and part/total like "(12/45)"
            if (! preg_match('/^\s*(?!"Usenet Index Post)(.+)\s+\((\d+)\/(\d+)\)/', $header['Subject'], $matches)) {
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

            $parsed[] = [
                'header' => $header,
                'repaired' => $partRepair,
            ];
        }

        return [
            'headers' => array_column($parsed, 'header'),
            'repaired' => $headersRepaired,
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
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function getArticleRange(array $headers): array
    {
        $result = [];
        $count = \count($headers);

        if ($count === 0) {
            return $result;
        }

        // Find first valid article
        for ($i = 0; $i < $count; $i++) {
            if (isset($headers[$i]['Number'])) {
                $result['firstArticleNumber'] = $headers[$i]['Number'];
                $result['firstArticleDate'] = $headers[$i]['Date'] ?? null;
                break;
            }
        }

        // Find last valid article
        for ($i = $count - 1; $i >= 0; $i--) {
            if (isset($headers[$i]['Number'])) {
                $result['lastArticleNumber'] = $headers[$i]['Number'];
                $result['lastArticleDate'] = $headers[$i]['Date'] ?? null;
                break;
            }
        }

        return $result;
    }
}
