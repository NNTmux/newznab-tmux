<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Binaries\Obfuscated\ObfuscatedFile;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadConfig;
use App\Services\Binaries\Obfuscated\ObfuscatedUploadPlanner;
use PHPUnit\Framework\TestCase;

final class ObfuscatedUploadPlannerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private const int HOUR = 3600;

    public function test_counted_files_of_one_upload_form_one_upload(): void
    {
        $files = [
            $this->file(tf: 3, fn: 1, tp: 10, firstArticle: 100),
            $this->file(tf: 3, fn: 2, tp: 10, firstArticle: 110),
            $this->file(tf: 3, fn: 3, tp: 2, firstArticle: 120),
        ];

        $uploads = $this->planner()->plan('bob@home.com', $files, self::NOW);

        $this->assertCount(1, $uploads);
        $this->assertCount(3, $uploads[0]->files);
        $this->assertSame(10, $uploads[0]->files[0]->totalParts, 'largest file first');
    }

    public function test_overlapping_uploads_with_the_same_file_count_are_chained_by_posting_order(): void
    {
        // Two uploads by one shared poster, interleaved in time but each posted contiguously.
        $files = [
            $this->file(tf: 2, fn: 1, tp: 10, firstArticle: 1000),
            $this->file(tf: 2, fn: 1, tp: 20, firstArticle: 5000),
            $this->file(tf: 2, fn: 2, tp: 30, firstArticle: 5020),
            $this->file(tf: 2, fn: 2, tp: 40, firstArticle: 1010),
        ];

        $uploads = $this->planner()->plan('bob@home.com', $files, self::NOW);

        $this->assertCount(2, $uploads);
        $this->assertSame([40, 10], array_map(static fn (ObfuscatedFile $file): int => $file->totalParts, $uploads[0]->files));
        $this->assertSame([30, 20], array_map(static fn (ObfuscatedFile $file): int => $file->totalParts, $uploads[1]->files));
    }

    public function test_counted_upload_missing_a_file_is_not_assembled(): void
    {
        $files = [
            $this->file(tf: 3, fn: 1, tp: 10, firstArticle: 100),
            $this->file(tf: 3, fn: 3, tp: 10, firstArticle: 120),
        ];

        $this->assertSame([], $this->planner()->plan('bob@home.com', $files, self::NOW));
    }

    public function test_upload_that_is_still_receiving_articles_waits(): void
    {
        $files = [$this->file(tf: 0, fn: 0, tp: 10, firstArticle: 100, lastSeen: self::NOW - 60)];

        $this->assertSame([], $this->planner()->plan('elys-1', $files, self::NOW));
    }

    public function test_upload_below_the_completion_threshold_waits(): void
    {
        $files = [$this->file(tf: 0, fn: 0, tp: 100, firstArticle: 100, present: 90)];

        $this->assertSame([], $this->planner()->plan('elys-1', $files, self::NOW));
    }

    public function test_uncounted_files_are_grouped_by_time(): void
    {
        $files = [
            $this->file(tf: 0, fn: 0, tp: 50, firstArticle: 100, time: self::NOW - 5 * self::HOUR),
            $this->file(tf: 0, fn: 0, tp: 3, firstArticle: 160, time: self::NOW - 5 * self::HOUR + 60),
            $this->file(tf: 0, fn: 0, tp: 40, firstArticle: 900, time: self::NOW - 2 * self::HOUR),
        ];

        $uploads = $this->planner()->plan('abc@def.invalid', $files, self::NOW);

        $this->assertSame([2, 1], array_map(static fn ($upload): int => \count($upload->files), $uploads));
    }

    public function test_repeated_key_is_split_by_time_gap_into_separate_files(): void
    {
        $first = $this->articles(parts: 4, firstArticle: 100, time: self::NOW - 5 * self::HOUR);
        $repost = $this->articles(parts: 4, firstArticle: 900, time: self::NOW - 2 * self::HOUR);

        $files = $this->planner()->splitDuplicateKey(2, 1, 4, [...$repost, ...$first], self::NOW - self::HOUR);

        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertTrue($file->isComplete());
            $this->assertFalse($file->ambiguous);
        }
        $this->assertSame([101, 901], [$files[0]->minNumber, $files[1]->minNumber]);
    }

    public function test_same_size_files_posted_together_are_ranked_and_marked_ambiguous(): void
    {
        $time = self::NOW - 2 * self::HOUR;
        $volumeA = $this->articles(parts: 3, firstArticle: 100, time: $time);
        $volumeB = $this->articles(parts: 3, firstArticle: 200, time: $time);

        $files = $this->planner()->splitDuplicateKey(0, 0, 3, [...$volumeB, ...$volumeA], self::NOW - self::HOUR);

        $this->assertCount(2, $files);
        $this->assertTrue($files[0]->ambiguous && $files[1]->ambiguous);
        $this->assertSame([101, 102, 103], $files[0]->binaryIds, 'lowest article numbers form the first file');
        $this->assertSame([201, 202, 203], $files[1]->binaryIds);
    }

    public function test_rank_policy_keeps_ambiguous_files_but_never_an_ambiguous_payload(): void
    {
        $payload = $this->file(tf: 0, fn: 0, tp: 50, firstArticle: 100);
        $volume = $this->file(tf: 0, fn: 0, tp: 5, firstArticle: 200, ambiguous: true);

        $kept = $this->planner(ObfuscatedUploadConfig::POLICY_RANK)->plan('p', [$payload, $volume], self::NOW);
        $ambiguousPayload = $this->planner(ObfuscatedUploadConfig::POLICY_RANK)->plan('p', [
            $this->file(tf: 0, fn: 0, tp: 50, firstArticle: 100, ambiguous: true),
            $volume,
        ], self::NOW);

        $this->assertCount(2, $kept[0]->files);
        $this->assertSame(1, $kept[0]->ambiguousFiles());
        $this->assertSame([], $ambiguousPayload);
    }

    public function test_drop_policy_removes_ambiguous_files_and_skip_policy_rejects_the_upload(): void
    {
        $files = [
            $this->file(tf: 0, fn: 0, tp: 50, firstArticle: 100),
            $this->file(tf: 0, fn: 0, tp: 5, firstArticle: 200, ambiguous: true),
        ];

        $dropped = $this->planner(ObfuscatedUploadConfig::POLICY_DROP)->plan('p', $files, self::NOW);
        $skipped = $this->planner(ObfuscatedUploadConfig::POLICY_SKIP)->plan('p', $files, self::NOW);

        $this->assertCount(1, $dropped[0]->files);
        $this->assertSame(0, $dropped[0]->ambiguousFiles());
        $this->assertSame([], $skipped);
    }

    public function test_upload_above_the_part_cap_is_left_alone(): void
    {
        $planner = new ObfuscatedUploadPlanner(new ObfuscatedUploadConfig(maxPartsPerUpload: 10));

        $this->assertSame([], $planner->plan('p', [$this->file(tf: 0, fn: 0, tp: 11, firstArticle: 1)], self::NOW));
    }

    private function planner(string $policy = ObfuscatedUploadConfig::POLICY_RANK): ObfuscatedUploadPlanner
    {
        return new ObfuscatedUploadPlanner(new ObfuscatedUploadConfig(enabled: true, ambiguousPolicy: $policy));
    }

    private function file(
        int $tf,
        int $fn,
        int $tp,
        int $firstArticle,
        ?int $present = null,
        int $time = self::NOW - 2 * self::HOUR,
        int $lastSeen = self::NOW - self::HOUR,
        bool $ambiguous = false,
    ): ObfuscatedFile {
        $present ??= $tp;

        return new ObfuscatedFile(
            totalFiles: $tf,
            fileNumber: $fn,
            totalParts: $tp,
            articles: $present,
            distinctParts: $present,
            minNumber: $firstArticle,
            maxNumber: $firstArticle + $present - 1,
            minTime: $time,
            maxTime: $time + $present,
            lastSeen: $lastSeen,
            bytes: $present * 700_000,
            ambiguous: $ambiguous,
        );
    }

    /**
     * @return list<array{binary: int, part: int, number: int, time: int, size: int}>
     */
    private function articles(int $parts, int $firstArticle, int $time): array
    {
        $articles = [];
        foreach (range(1, $parts) as $part) {
            $articles[] = [
                'binary' => $firstArticle + $part,
                'part' => $part,
                'number' => $firstArticle + $part,
                'time' => $time + $part,
                'size' => 700_000,
            ];
        }

        return $articles;
    }
}
