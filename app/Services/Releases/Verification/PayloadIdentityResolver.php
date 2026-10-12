<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\NameFixing\PredbIdentityKey;
use App\Services\ReleaseCleaningService;

final class PayloadIdentityResolver
{
    public function __construct(private readonly ReleaseCleaningService $cleaning) {}

    /** @param array<string, mixed> $manifest */
    public function resolve(array $manifest): ?string
    {
        $hints = $manifest['identity_hints'] ?? [];
        if (! is_array($hints) || count($hints) > 4096) {
            return null;
        }
        $names = [];
        foreach ($hints as $hint) {
            if (! is_array($hint) || ! in_array($hint['source'] ?? null, ['yenc', 'par2'], true) || ! is_string($hint['name'] ?? null)) {
                continue;
            }
            $filename = $hint['name'];
            if (preg_match('/(?:^|[._\-\s])(?:sample|proof|thumbs?|subs?|subtitles)(?:[._\-\s]|$)/i', $filename)) {
                continue;
            }
            $name = $this->cleaning->releaseNameFromFile(str_replace('_', ' ', $filename));
            if ($name === null || preg_match('/\b(?:s\d{1,3}e\d{1,3}|(?:19|20)\d{2}|720p|1080p|2160p|flac|mp3|\d{2,3}kbps|v\d+\.\d+)\b/i', $name) !== 1) {
                continue;
            }
            $key = PredbIdentityKey::normalize($name);
            if ($key === null) {
                continue;
            }
            $names[$key] ??= $name;
            if (count($names) > 1) {
                return null;
            }
        }

        return count($names) === 1 ? reset($names) : null;
    }
}
