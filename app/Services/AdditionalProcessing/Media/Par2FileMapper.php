<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Media;

use App\Services\AdditionalProcessing\DTO\MappedNzbFile;
use App\Services\AdditionalProcessing\DTO\Par2FileEntry;
use App\Services\AdditionalProcessing\Enums\MappingConfidence;

/**
 * Matches the files of an NZB to the real names in its PAR2 index.
 *
 * An NZB only knows the posted size of each file: its yEnc-encoded articles,
 * a few percent larger than the file itself. A PAR2 entry is matched to an NZB
 * file when that file is the only one whose posted size fits it, and it is the
 * only entry that fits the file. Equal-sized archive volumes stay unmatched
 * unless their first 16 KiB was checked against the index.
 */
final readonly class Par2FileMapper
{
    /** Smallest real size, relative to the posted size, still taken as the same file. */
    private const float MIN_SIZE_RATIO = 0.90;

    /** Posted sizes are sometimes the decoded size; allow a little rounding above it. */
    private const float MAX_SIZE_RATIO = 1.001;

    /**
     * @param  array<int|string, mixed>  $nzbContents
     * @param  list<Par2FileEntry>  $files
     * @param  array<int, Par2FileEntry>  $verified  files whose start was already matched, keyed by source index
     * @return array<int, MappedNzbFile> keyed by source index
     */
    public function map(array $nzbContents, array $files, ?int $articlePartSize = null, array $verified = []): array
    {
        $mapped = [];
        foreach ($verified as $sourceIndex => $file) {
            $mapped[$sourceIndex] = new MappedNzbFile($sourceIndex, $file, MappingConfidence::Verified);
        }

        $remainingFiles = array_values(array_filter(
            $files,
            static fn (Par2FileEntry $file): bool => ! in_array($file, $verified, true),
        ));

        $fitsByFile = [];
        $fitsByEntry = [];
        foreach ($remainingFiles as $fileIndex => $file) {
            foreach ($this->candidateIndexes($nzbContents, $file, $articlePartSize) as $sourceIndex) {
                if (isset($mapped[$sourceIndex])) {
                    continue;
                }
                $fitsByFile[$fileIndex][] = $sourceIndex;
                $fitsByEntry[$sourceIndex][] = $fileIndex;
            }
        }

        foreach ($fitsByFile as $fileIndex => $sourceIndexes) {
            $sourceIndex = $sourceIndexes[0];
            if (count($sourceIndexes) === 1 && count($fitsByEntry[$sourceIndex]) === 1) {
                $mapped[$sourceIndex] = new MappedNzbFile($sourceIndex, $remainingFiles[$fileIndex], MappingConfidence::UniqueSize);
            }
        }

        ksort($mapped);

        return $mapped;
    }

    /**
     * NZB files whose posted size, and segment count when the article size is known, fit a PAR2 entry.
     *
     * @param  array<int|string, mixed>  $nzbContents
     * @return list<int> source indexes
     */
    public function candidateIndexes(array $nzbContents, Par2FileEntry $file, ?int $articlePartSize = null): array
    {
        $indexes = [];
        foreach (array_values($nzbContents) as $sourceIndex => $entry) {
            if (! $this->isSingleFile($entry)) {
                continue;
            }

            $postedSize = (int) $entry['size'];
            $ratio = $file->size / $postedSize;
            if ($ratio < self::MIN_SIZE_RATIO || $ratio > self::MAX_SIZE_RATIO) {
                continue;
            }

            if ($articlePartSize !== null && $articlePartSize > 0
                && (int) ceil($file->size / $articlePartSize) !== count($entry['segments'])
            ) {
                continue;
            }

            $indexes[] = $sourceIndex;
        }

        return $indexes;
    }

    /**
     * Stripped subjects can merge several NZB files into one entry, whose size is then wrong.
     *
     * @phpstan-assert-if-true array{size: int|string, segments: non-empty-array<int|string, mixed>} $entry
     */
    private function isSingleFile(mixed $entry): bool
    {
        return is_array($entry)
            && (int) ($entry['filecount'] ?? 1) === 1
            && (int) ($entry['size'] ?? 0) > 0
            && is_array($entry['segments'] ?? null)
            && $entry['segments'] !== [];
    }
}
