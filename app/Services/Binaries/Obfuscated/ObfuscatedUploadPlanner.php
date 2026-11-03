<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

/**
 * Decides which rebuilt files form an upload. Pure logic over values loaded by
 * {@see ObfuscatedUploadAssembler}, so every rule is unit-testable without a database.
 *
 * - Files that declare a `[fn/tf]` counter are chained fn 1..tf by article-number
 *   proximity, because one poster (e.g. `bob@home.com`) may run several uploads at once.
 * - Files without a counter come from per-upload posters and are grouped by time.
 */
final readonly class ObfuscatedUploadPlanner
{
    public function __construct(private ObfuscatedUploadConfig $config) {}

    /**
     * Split one (tf, fn, tp) key whose part numbers repeat: first by time gaps (separate
     * uploads of the same shape), then, if parts still repeat, by posting order (same-size
     * files of one upload, e.g. PAR2 recovery volumes). Posting-order files are marked ambiguous.
     *
     * @param  list<array{binary: int, part: int, number: int, time: int, size: int}>  $articles
     * @return list<ObfuscatedFile>
     */
    public function splitDuplicateKey(int $totalFiles, int $fileNumber, int $totalParts, array $articles, int $lastSeen, bool $counterIsPart = false): array
    {
        usort($articles, static fn (array $a, array $b): int => [$a['time'], $a['number']] <=> [$b['time'], $b['number']]);

        $segments = [];
        $current = [];
        $previousTime = null;
        foreach ($articles as $article) {
            if ($previousTime !== null && $this->gapSeconds() < $article['time'] - $previousTime) {
                $segments[] = $current;
                $current = [];
            }
            $current[] = $article;
            $previousTime = $article['time'];
        }
        if ($current !== []) {
            $segments[] = $current;
        }

        $files = [];
        foreach ($segments as $segment) {
            $byPart = [];
            foreach ($segment as $article) {
                $byPart[$article['part']][] = $article;
            }
            $copies = max(array_map('count', $byPart));
            if ($copies === 1) {
                $files[] = $this->fileFromArticles($totalFiles, $fileNumber, $totalParts, $segment, $lastSeen, false, $counterIsPart);

                continue;
            }

            $ranked = array_fill(0, $copies, []);
            foreach ($byPart as $occurrences) {
                usort($occurrences, static fn (array $a, array $b): int => $a['number'] <=> $b['number']);
                foreach ($occurrences as $rank => $article) {
                    $ranked[$rank][] = $article;
                }
            }
            foreach ($ranked as $members) {
                $files[] = $this->fileFromArticles($totalFiles, $fileNumber, $totalParts, $members, $lastSeen, true, $counterIsPart);
            }
        }

        return $files;
    }

    /**
     * @param  list<ObfuscatedFile>  $files  every rebuilt file of one poster
     * @return list<ObfuscatedUpload> uploads ready to merge
     */
    public function plan(string $poster, array $files, int $now): array
    {
        $byTotalFiles = [];
        foreach ($files as $file) {
            if ($this->config->ambiguousPolicy === ObfuscatedUploadConfig::POLICY_DROP && $file->ambiguous) {
                continue;
            }
            $byTotalFiles[$file->totalFiles][] = $file;
        }

        $uploads = [];
        foreach ($byTotalFiles as $totalFiles => $group) {
            $candidates = $totalFiles > 0
                ? $this->chainCountedFiles((int) $totalFiles, $group)
                : $this->clusterUncountedFiles($group);
            foreach ($candidates as $members) {
                $upload = new ObfuscatedUpload($poster, (int) $totalFiles, $members);
                if ($this->accepts($upload, $now)) {
                    $uploads[] = $upload;
                }
            }
        }

        usort($uploads, static fn (ObfuscatedUpload $a, ObfuscatedUpload $b): int => $a->minNumber() <=> $b->minNumber());

        return $uploads;
    }

    public function accepts(ObfuscatedUpload $upload, int $now): bool
    {
        if ($upload->files === [] || $upload->lastSeen() >= $now - $this->config->quietMinutes * 60) {
            return false;
        }
        if ($upload->articles() > $this->config->maxPartsPerUpload) {
            return false;
        }
        if ($upload->ambiguousFiles() > 0 && $this->config->ambiguousPolicy === ObfuscatedUploadConfig::POLICY_SKIP) {
            return false;
        }
        // The largest file is the payload; posting order cannot be trusted to rebuild it.
        if ($upload->files[0]->ambiguous) {
            return false;
        }

        return $upload->completionPercent() >= $this->config->minCompletionPercent;
    }

    /**
     * Follow fn 1..tf from each file 1, always taking the next file posted closest after the
     * previous one. Only a chain that has every fn exactly once is an upload.
     *
     * @param  list<ObfuscatedFile>  $files
     * @return list<list<ObfuscatedFile>>
     */
    private function chainCountedFiles(int $totalFiles, array $files): array
    {
        $byFileNumber = [];
        foreach ($files as $index => $file) {
            if ($file->fileNumber >= 1 && $file->fileNumber <= $totalFiles) {
                $byFileNumber[$file->fileNumber][$index] = $file;
            }
        }
        $starts = $byFileNumber[1] ?? [];
        uasort($starts, static fn (ObfuscatedFile $a, ObfuscatedFile $b): int => $a->minNumber <=> $b->minNumber);

        $used = [];
        $chains = [];
        foreach ($starts as $startIndex => $start) {
            if (isset($used[$startIndex])) {
                continue;
            }
            $chain = [$startIndex => $start];
            $previous = $start;
            for ($fileNumber = 2; $fileNumber <= $totalFiles; $fileNumber++) {
                $bestIndex = null;
                $bestDistance = PHP_INT_MAX;
                foreach ($byFileNumber[$fileNumber] ?? [] as $index => $candidate) {
                    if (isset($used[$index])
                        || $candidate->minTime > $previous->maxTime + $this->gapSeconds()
                        || $candidate->maxTime < $previous->minTime - $this->gapSeconds()) {
                        continue;
                    }
                    $distance = abs($candidate->minNumber - $previous->maxNumber);
                    if ($distance < $bestDistance) {
                        $bestDistance = $distance;
                        $bestIndex = $index;
                    }
                }
                if ($bestIndex === null) {
                    continue 2;
                }
                $chain[$bestIndex] = $byFileNumber[$fileNumber][$bestIndex];
                $previous = $chain[$bestIndex];
            }

            foreach (array_keys($chain) as $index) {
                $used[$index] = true;
            }
            $chains[] = array_values($chain);
        }

        return $chains;
    }

    /**
     * @param  list<ObfuscatedFile>  $files
     * @return list<list<ObfuscatedFile>>
     */
    private function clusterUncountedFiles(array $files): array
    {
        usort($files, static fn (ObfuscatedFile $a, ObfuscatedFile $b): int => [$a->minTime, $a->minNumber] <=> [$b->minTime, $b->minNumber]);

        $clusters = [];
        $current = [];
        $clusterEnd = null;
        foreach ($files as $file) {
            if ($clusterEnd !== null && $file->minTime > $clusterEnd + $this->gapSeconds()) {
                $clusters[] = $current;
                $current = [];
                $clusterEnd = null;
            }
            $current[] = $file;
            $clusterEnd = max($clusterEnd ?? $file->maxTime, $file->maxTime);
        }
        if ($current !== []) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    /**
     * @param  list<array{binary: int, part: int, number: int, time: int, size: int}>  $articles
     */
    private function fileFromArticles(int $totalFiles, int $fileNumber, int $totalParts, array $articles, int $lastSeen, bool $ambiguous, bool $counterIsPart): ObfuscatedFile
    {
        $numbers = array_column($articles, 'number');
        $times = array_column($articles, 'time');

        return new ObfuscatedFile(
            totalFiles: $totalFiles,
            fileNumber: $fileNumber,
            totalParts: $totalParts,
            articles: \count($articles),
            distinctParts: \count(array_unique(array_column($articles, 'part'))),
            minNumber: min($numbers),
            maxNumber: max($numbers),
            minTime: min($times),
            maxTime: max($times),
            lastSeen: $lastSeen,
            bytes: array_sum(array_column($articles, 'size')),
            ambiguous: $ambiguous,
            binaryIds: array_values(array_map('intval', array_column($articles, 'binary'))),
            counterIsPart: $counterIsPart,
        );
    }

    private function gapSeconds(): int
    {
        return $this->config->gapMinutes * 60;
    }
}
