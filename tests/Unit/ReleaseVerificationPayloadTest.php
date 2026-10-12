<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Releases\Verification\ArticleSource;
use App\Services\Releases\Verification\NzbManifestReader;
use App\Services\Releases\Verification\PayloadVerifier;
use App\Services\Releases\Verification\VerifiedArticle;
use App\Services\Releases\Verification\VerifiedNzbWriter;
use App\Services\YencService;
use dariusiii\rarinfo\Par2Info;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Fixtures\YencArticles;

class ReleaseVerificationPayloadTest extends TestCase
{
    public function test_decodes_validated_single_article_with_identity_and_offsets(): void
    {
        $raw = (new YencService)->encode('hello', 'movie.mkv');
        $article = VerifiedArticle::decode($raw);
        $this->assertSame('movie.mkv', $article->name);
        $this->assertSame(5, $article->size);
        $this->assertSame(0, $article->offset);
        $this->assertSame('hello', $article->bytes);
    }

    public function test_payload_requires_every_declared_file(): void
    {
        [$verifier, $directory] = $this->verifier(['a@b' => (new YencService)->encode('hello', 'movie.mkv')]);
        try {
            $result = $verifier->verify($this->manifest(2), $directory, static function (): void {});
            $this->assertFalse($result->passed());
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_full_download_is_required_and_unavailable_articles_remain_unknown(): void
    {
        [$verifier, $directory] = $this->verifier([]);
        try {
            $result = $verifier->verify($this->manifest(1), $directory, static function (): void {});
            $this->assertSame('unknown', $result->verdict);
            $this->assertArrayNotHasKey('identity_hints', $result->manifest);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_verified_download_passes_and_resumes_from_checksum_checked_article_cache(): void
    {
        [$verifier, $directory] = $this->verifier(['a@b' => (new YencService)->encode('hello', 'movie.mkv')]);
        try {
            $this->assertTrue($verifier->verify($this->manifest(1), $directory, static function (): void {})->passed());
            [$offline] = $this->verifier([]);
            $this->assertTrue($offline->verify($this->manifest(1), $directory, static function (): void {})->passed());
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_single_file_verified_nzb_without_subject_counters_can_be_verified_again(): void
    {
        $articles = ['a@b' => (new YencService)->encode('hello', 'movie.mkv')];
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', 'hello');
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{string, list<int>, list<string>, list<int>, int, bool}> */
    public static function nzbSegmentInventories(): iterable
    {
        yield 'single complete article without part suffix' => ['[1/1] movie.mkv', [1], ['hello'], [1], 1, true];
        yield 'multipart complete bytes without part suffix' => ['[1/1] movie.mkv', [1, 2], ['hell', 'tail'], [1, 5], 2, true];
        yield 'duplicate suffix-free numbers cannot replace missing part two' => ['[1/1] movie.mkv', [1, 1, 3], ['aa', 'bb', 'cccc'], [1, 3, 5], 3, false];
        yield 'duplicate explicitly declared numbers cannot replace missing part two' => ['[1/1] movie.mkv (1/3)', [1, 1, 3], ['aa', 'bb', 'cccc'], [1, 3, 5], 3, false];
        yield 'gap in numbered parts despite complete byte ranges' => ['[1/1] movie.mkv', [1, 3], ['hell', 'tail'], [1, 5], 3, false];
        yield 'missing byte range despite consecutive part numbers' => ['[1/1] movie.mkv', [1, 2], ['he', 'tail'], [1, 5], 2, false];
        yield 'explicit larger total must not be inferred down' => ['[1/1] movie.mkv (1/3)', [1, 2], ['hell', 'tail'], [1, 5], 3, false];
        yield 'explicit zero total remains invalid' => ['[1/1] movie.mkv (1/0)', [1], ['hello'], [1], 0, false];
        yield 'stronger overall file count remains unresolved' => ['[1/2] movie.mkv', [1], ['hello'], [1], 1, false];
        yield 'absent overall file inventory remains unknown' => ['movie.mkv', [1], ['hello'], [1], 1, false];
    }

    /**
     * @param  list<int>  $numbers
     * @param  list<string>  $pieces
     * @param  list<int>  $begins
     */
    #[DataProvider('nzbSegmentInventories')]
    public function test_nzb_without_part_suffix_derives_only_part_inventory_and_requires_actual_complete_bytes(string $subject, array $numbers, array $pieces, array $begins, int $expectedParts, bool $passes): void
    {
        $size = count($numbers) === 1 ? strlen($pieces[0]) : 8;
        $articles = [];
        $segments = '';
        foreach ($numbers as $index => $number) {
            $id = 'part-'.$number.'-'.$index.'@article';
            $articles[$id] = count($numbers) === 1 ? (new YencService)->encode($pieces[$index], 'movie.mkv')
                : YencArticles::part($pieces[$index], 'movie.mkv', $size, $number, $begins[$index], max($numbers));
            $segments .= '<segment bytes="'.strlen($pieces[$index]).'" number="'.$number.'">'.$id.'</segment>';
        }
        $xml = '<nzb><file subject="'.$subject.'"><groups><group>alt.test</group></groups><segments>'.$segments.'</segments></file></nzb>';
        $manifest = (new NzbManifestReader)->read($xml, (object) ['searchname' => 'fixture', 'fromname' => 'fixture',
            'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1]);
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $this->assertSame($expectedParts, $manifest['files'][0]['totalparts']);
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            if ($result->passed() && count(array_unique($numbers)) !== count($numbers)) {
                $this->assertSame(implode('', $pieces), file_get_contents($scratch.'/data/movie.mkv'));
                $writer = (new \ReflectionClass(VerifiedNzbWriter::class))->newInstanceWithoutConstructor();
                $emitted = (new \ReflectionMethod($writer, 'document'))->invoke($writer, $result->manifest);
                $decoded = simplexml_load_string($emitted);
                $emittedNumbers = [];
                foreach ($decoded->file[0]->segments->segment as $segment) {
                    $emittedNumbers[] = (int) $segment['number'];
                }
                $this->assertSame($numbers, $emittedNumbers, 'The false pass emitted duplicated segment numbers despite correct contiguous decoded bytes.');
            }
            $this->assertSame($passes, $result->passed(), $result->reason);
            if (count(array_unique($numbers)) !== count($numbers)) {
                unset($manifest['conflict']);
                $direct = $verifier->verify($manifest, $scratch, static function (): void {});
                $this->assertFalse($direct->passed(), 'Verifier must independently reject duplicate ordinals without reader metadata.');
                $this->assertSame('Invalid or duplicate article part number.', $direct->reason);
            }
            if ($passes) {
                $this->assertSame(implode('', $pieces), file_get_contents($scratch.'/data/movie.mkv'));
                $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', implode('', $pieces));
            }
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{int, bool, bool, 3?: int, 4?: bool, 5?: bool}> */
    public static function retainedCacheBudgets(): iterable
    {
        yield 'cached first projection preserves free reserve one byte short' => [0, true, false, -1];
        yield 'cached exact free reserve does not charge visits again' => [0, true, true, 0];
        yield 'new checkpoint exact live free reserve charged only once' => [0, false, true, 0];
        yield 'successful corrupt cache eviction refunds baseline before replacement' => [0, true, true, 0, true];
        yield 'stale checkpoint temporary is refunded before replacement' => [0, false, true, 0, false, true];
        yield 'all cached exceeds projected scratch by one byte' => [-1, true, false];
        yield 'all cached fits exact projection without double counting visits' => [0, true, true];
        yield 'new first checkpoint and retained tail fits exact projection' => [0, false, true];
        yield 'new first checkpoint and retained tail exceeds by one byte' => [-1, false, false];
    }

    #[DataProvider('retainedCacheBudgets')]
    public function test_retained_raw_cache_is_counted_before_derived_allocation_and_new_checkpoints_only_once(int $extraBudget, bool $firstCached, bool $passes, ?int $extraFree = null, bool $corruptFirst = false, bool $staleTemporary = false): void
    {
        $raw = ['first@article' => YencArticles::part('a', 'movie.mkv', 1000, 1, 1, 2),
            'tail@article' => YencArticles::part(str_repeat('b', 999), 'movie.mkv', 1000, 2, 2, 2)];
        $source = new class($raw) implements ArticleSource
        {
            /** @var list<string> */
            public array $requested = [];

            /** @param array<string, string> $raw */
            public function __construct(private readonly array $raw) {}

            public function fetch(string $messageId): string
            {
                $this->requested[] = $messageId;

                return $this->raw[$messageId];
            }
        };
        [$unused, $scratch] = $this->verifier([]);
        mkdir($scratch.'/articles');
        foreach ($raw as $id => $body) {
            if ($firstCached || $id === 'tail@article') {
                $stored = $corruptFirst && $id === 'first@article' ? preg_replace('/pcrc32=[0-9a-f]+/', 'pcrc32=00000000', $body) : $body;
                file_put_contents($scratch.'/articles/'.hash('sha256', $id), $stored);
            }
        }
        if ($staleTemporary) {
            file_put_contents($scratch.'/articles/'.hash('sha256', 'first@article').'.tmp', str_repeat('x', 500));
        }
        $rawBytes = array_sum(array_map(strlen(...), $raw));
        $budget = $rawBytes + 3000 + $extraBudget;
        $reserve = $extraFree === null ? 0 : 1234;
        $probe = null;
        if ($extraFree !== null) {
            $initialCache = array_sum(array_map(filesize(...), glob($scratch.'/articles/*')));
            $initialFree = $reserve + 3000 + max(0, $rawBytes - $initialCache) + $extraFree;
            $probe = static function (string $directory) use ($initialCache, $initialFree): float {
                $currentCache = array_sum(array_map(filesize(...), glob($directory.'/articles/*')));

                return (float) ($initialFree + $initialCache - $currentCache);
            };
        }
        $verifier = new PayloadVerifier($source, ['scratch_bytes' => $budget, 'free_bytes' => $reserve,
            'article_bytes' => 16777216, 'bytes_per_second' => 0, 'par2_binary' => 'par2'], $probe);
        $manifest = ['expected_files' => 1, 'files' => [['key' => 'source', 'subject' => 'movie.mkv', 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'first@article', 'bytes' => 1],
                ['number' => 2, 'messageid' => 'tail@article', 'bytes' => 999]]]]];
        try {
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            $this->assertSame($passes, $result->passed(), $result->reason);
            $this->assertSame($firstCached && ! $corruptFirst ? [] : ['first@article'], $source->requested);
            if ($passes) {
                $this->assertSame('a'.str_repeat('b', 999), file_get_contents($scratch.'/data/movie.mkv'));
            } else {
                $this->assertSame('Verification is waiting for scratch capacity.', $result->reason);
                $this->assertFileDoesNotExist($scratch.'/data/movie.mkv', 'Full retained cache must be charged before the first decoded file allocation.');
            }
            foreach ($raw as $id => $body) {
                $this->assertSame($body, file_get_contents($scratch.'/articles/'.hash('sha256', $id)));
            }
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function linkedScratchDirectories(): iterable
    {
        yield 'candidate directory symlink' => [true];
        yield 'article cache directory symlink' => [false];
    }

    #[DataProvider('linkedScratchDirectories')]
    public function test_symlinked_candidate_or_cache_root_cannot_touch_outside_sentinels_or_fetch_articles(bool $candidateLinked): void
    {
        [$unused, $scratch] = $this->verifier([]);
        $source = new class implements ArticleSource
        {
            public int $fetches = 0;

            public function fetch(string $messageId): string
            {
                $this->fetches++;

                return (new YencService)->encode('hello', 'movie.mkv');
            }
        };
        mkdir($scratch.'/outside');
        if ($candidateLinked) {
            mkdir($scratch.'/outside/data');
            $sentinel = $scratch.'/outside/data/sentinel';
            $directory = $scratch.'/current-link';
            $link = $directory;
        } else {
            $sentinel = $scratch.'/outside/sentinel';
            $directory = $scratch;
            $link = $scratch.'/articles';
        }
        file_put_contents($sentinel, 'preserve outside data');
        symlink($scratch.'/outside', $link);
        $verifier = new PayloadVerifier($source, ['scratch_bytes' => 1048576, 'free_bytes' => 0, 'article_bytes' => 16777216,
            'bytes_per_second' => 0, 'par2_binary' => 'par2']);
        try {
            $result = $verifier->verify($this->manifest(1), $directory, static function (): void {});
            $this->assertSame('unknown', $result->verdict);
            $this->assertSame(0, $source->fetches);
            $this->assertSame('preserve outside data', file_get_contents($sentinel));
            $this->assertFileDoesNotExist($directory.'/data/movie.mkv');
        } finally {
            unlink($link);
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{int, int, int, bool}> */
    public static function yencFileTotals(): iterable
    {
        yield 'body three contradicts explicit two' => [2, 3, 3, false];
        yield 'frame totals disagree' => [2, 2, 3, false];
        yield 'consistent explicit two passes' => [2, 2, 2, true];
        yield 'missing third part remains incomplete without parity' => [3, 3, 3, false];
    }

    #[DataProvider('yencFileTotals')]
    public function test_yenc_part_totals_must_agree_with_frames_and_explicit_nzb_inventory(int $manifestParts, int $firstTotal, int $secondTotal, bool $passes): void
    {
        $articles = ['first@article' => YencArticles::part('hell', 'movie.mkv', 8, 1, 1, $firstTotal),
            'tail@article' => YencArticles::part('tail', 'movie.mkv', 8, 2, 5, $secondTotal)];
        $xml = '<nzb><file subject="[1/1] movie.mkv (1/'.$manifestParts.')"><groups><group>alt.test</group></groups><segments>'
            .'<segment number="1" bytes="4">first@article</segment><segment number="2" bytes="4">tail@article</segment></segments></file></nzb>';
        $manifest = (new NzbManifestReader)->read($xml, (object) ['searchname' => 'fixture', 'fromname' => 'fixture',
            'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1]);
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            if ($result->passed()) {
                $this->assertSame('helltail', file_get_contents($scratch.'/data/movie.mkv'));
            }
            $this->assertSame($passes, $result->passed(), $result->reason);
            if ($passes) {
                $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', 'helltail');
            }
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidYencTotals(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'not decimal' => ['two'];
        yield 'empty' => [''];
        yield 'numeric overflow' => [str_repeat('9', 19)];
        yield 'part exceeds total' => ['1'];
    }

    #[DataProvider('invalidYencTotals')]
    public function test_yenc_optional_total_must_be_positive_bounded_and_cover_its_part(string $total): void
    {
        $raw = str_replace('total=2', 'total='.$total, YencArticles::part('tail', 'movie.mkv', 8, 2, 5, 2));
        $this->expectException(\RuntimeException::class);
        VerifiedArticle::decode($raw);
    }

    public function test_inferred_nzb_part_count_can_expand_from_consistent_yenc_totals_for_real_partial_par2_repair_and_replay(): void
    {
        $bytes = str_repeat('movie-data', 64);
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', $bytes, 'set');
        $piece = intdiv(strlen($bytes), 3);
        $articles['source-1@article'] = YencArticles::part(substr($bytes, 0, $piece), 'movie.mkv', strlen($bytes), 1, 1, 3);
        $articles['source-2@article'] = YencArticles::part(substr($bytes, $piece, $piece), 'movie.mkv', strlen($bytes), 2, $piece + 1, 3);
        $files[0]['segments'] = [['number' => 1, 'messageid' => 'source-1@article', 'bytes' => $piece],
            ['number' => 2, 'messageid' => 'source-2@article', 'bytes' => $piece]];
        $xml = '<nzb>';
        foreach ($files as $index => $file) {
            $xml .= '<file subject="['.($index + 1).'/'.count($files).'] '.$file['subject'].'"><groups><group>alt.test</group></groups><segments>';
            foreach ($file['segments'] as $segment) {
                $xml .= '<segment number="'.$segment['number'].'" bytes="'.$segment['bytes'].'">'.$segment['messageid'].'</segment>';
            }
            $xml .= '</segments></file>';
        }
        $xml .= '</nzb>';
        $manifest = (new NzbManifestReader)->read($xml, (object) ['searchname' => 'fixture', 'fromname' => 'fixture',
            'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1]);
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $this->assertSame(2, $manifest['files'][0]['totalparts']);
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertSame(3, $result->manifest['files'][0]['totalparts'], 'Published subject total must agree with the available bodies without inventing missing article IDs.');
            $this->assertSame([1, 2], array_column($result->manifest['files'][0]['segments'], 'number'));
            $this->assertSame($bytes, file_get_contents($scratch.'/data/movie.mkv'));
            $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', $bytes);
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_filename_total_text_is_not_header_inventory_and_absent_total_singlepart_remains_valid(): void
    {
        $raw = (new YencService)->encode('hello', 'movie total=0.mkv');
        $article = VerifiedArticle::decode($raw);
        $this->assertNull($article->totalParts);
        $this->assertSame('movie total=0.mkv', $article->name);
        $this->assertSame('hello', $article->bytes);
    }

    public function test_singlepart_frame_with_explicit_zero_total_is_invalid_even_without_multipart_range(): void
    {
        $raw = str_replace('=ybegin ', '=ybegin total=0 ', (new YencService)->encode('hello', 'movie.mkv'));
        $this->expectException(\RuntimeException::class);
        VerifiedArticle::decode($raw);
    }

    public function test_oversized_cached_article_is_evicted_even_when_provider_is_unavailable(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        mkdir($scratch.'/articles');
        $path = $scratch.'/articles/'.hash('sha256', 'a@b');
        file_put_contents($path, str_repeat('x', 16777217));
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertSame('unknown', $result->verdict);
            $this->assertFileDoesNotExist($path);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_corrupt_cache_is_evicted_and_replaced_only_after_successful_validation(): void
    {
        $good = (new YencService)->encode('hello', 'movie.mkv');
        $bad = str_replace('crc32=3610a686', 'crc32=00000000', $good);
        [$verifier, $scratch] = $this->verifier(['a@b' => $good]);
        mkdir($scratch.'/articles');
        $path = $scratch.'/articles/'.hash('sha256', 'a@b');
        file_put_contents($path, $bad);
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertSame($good, file_get_contents($path));
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function semanticCheckpoints(): iterable
    {
        yield 'wrong part from provider' => [false, false];
        yield 'wrong declared total from provider' => [true, false];
        yield 'persisted wrong part from earlier verifier' => [false, true];
        yield 'persisted wrong declared total from earlier verifier' => [true, true];
    }

    #[DataProvider('semanticCheckpoints')]
    public function test_semantically_invalid_articles_never_persist_and_old_checkpoints_can_refetch(bool $wrongTotal, bool $persisted): void
    {
        $first = YencArticles::part('hell', 'movie.mkv', 8, 1, 1, 2);
        $good = YencArticles::part('tail', 'movie.mkv', 8, 2, 5, 2);
        $bad = YencArticles::part('tail', 'movie.mkv', 8, $wrongTotal ? 2 : 1, 5, $wrongTotal ? 3 : 2);
        $this->assertSame('tail', VerifiedArticle::decode($bad)->bytes, 'Fixture has a valid checksum despite the wrong semantic identity.');
        $manifest = ['expected_files' => 1, 'files' => [['key' => 'source', 'subject' => 'movie.mkv', 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'first@article', 'bytes' => 4],
                ['number' => 2, 'messageid' => 'tail@article', 'bytes' => 4]]]]];
        $articles = ['first@article' => $first, 'tail@article' => $persisted ? $good : $bad];
        [$verifier, $scratch] = $this->verifier($articles);
        $path = $scratch.'/articles/'.hash('sha256', 'tail@article');
        if ($persisted) {
            mkdir($scratch.'/articles');
            file_put_contents($path, $bad);
        }
        try {
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            if (! $persisted) {
                $this->assertSame('unknown', $result->verdict);
                $this->assertFileDoesNotExist($path);
                [$corrected] = $this->verifier(['tail@article' => $good]);
                $result = $corrected->verify($manifest, $scratch, static function (): void {});
            }
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertSame($good, file_get_contents($path));
            $this->assertSame('helltail', file_get_contents($scratch.'/data/movie.mkv'));
            $this->assertPublishedArticlesCanRepair($result->manifest, ['first@article' => $first, 'tail@article' => $good], 'movie.mkv', 'helltail');
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_corrupt_download_with_insufficient_parity_is_unknown_and_never_cached(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'set');
        $files = array_values(array_filter($files, static fn (array $file): bool => ! str_contains($file['subject'], '.vol')));
        $articles['source@article'] = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', (new YencService)->encode(str_repeat('movie-data', 64), 'movie.mkv'));
        $this->assertNotSame($articles['source@article'], (new YencService)->encode(str_repeat('movie-data', 64), 'movie.mkv'));
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('unknown', $result->verdict, $result->reason);
            $this->assertFileDoesNotExist($scratch.'/articles/'.hash('sha256', 'source@article'));
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function staleProtectedSources(): iterable
    {
        yield 'index-only manifest with unknown declared count' => [false];
        yield 'source headers remain but source raw cache was lost' => [true];
    }

    #[DataProvider('staleProtectedSources')]
    public function test_stale_derived_source_cannot_make_index_only_articles_pass_fresh_nzb_replay(bool $sourceDeclared): void
    {
        $bytes = str_repeat('movie-data', 64);
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', $bytes, 'set');
        $indexFiles = array_values(array_filter($files, static fn (array $file): bool => $file['subject'] === 'set.par2'));
        $indexId = $indexFiles[0]['segments'][0]['messageid'];
        $articles = [$indexId => $articles[$indexId]];
        [$verifier, $scratch] = $this->verifier($articles);
        mkdir($scratch.'/data');
        file_put_contents($scratch.'/data/movie.mkv', $bytes);
        $manifest = ['expected_files' => 0, 'files' => $sourceDeclared ? array_merge([$files[0]], $indexFiles) : $indexFiles];
        $fresh = null;
        try {
            $resumed = $verifier->verify($manifest, $scratch, static function (): void {});
            $available = $resumed->passed() ? $resumed->manifest : ['expected_files' => 0, 'files' => $indexFiles];
            $available += ['fromname' => 'fixture', 'date' => '2026-10-11 12:00:00', 'groups' => ['alt.test']];
            $writer = (new \ReflectionClass(VerifiedNzbWriter::class))->newInstanceWithoutConstructor();
            $xml = (new \ReflectionMethod($writer, 'document'))->invoke($writer, $available);
            $parsed = (new NzbManifestReader)->read($xml, (object) ['searchname' => 'fixture', 'fromname' => 'fixture',
                'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1]);
            $this->assertSame([$indexId], array_merge(...array_map(static fn (array $file): array => array_column($file['segments'], 'messageid'), $parsed['files'])));
            [$replay, $fresh] = $this->verifier($articles);
            $freshResult = $replay->verify($parsed, $fresh, static function (): void {});
            $this->assertFalse($freshResult->passed(), 'An index alone cannot reconstruct its absent protected source.');
            $this->assertFalse($resumed->passed(), 'Resumed scratch passed although its emitted article inventory fails fresh replay.');
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
            if ($fresh !== null) {
                $this->removeDirectory($fresh);
            }
        }
    }

    public function test_actual_par2_repair_rebuilds_derived_files_from_preserved_valid_raw_cache_and_replays(): void
    {
        $bytes = str_repeat('movie-data', 64);
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', $bytes, 'set');
        $manifest = ['expected_files' => 0, 'files' => array_slice($files, 1)];
        [$verifier, $scratch] = $this->verifier($articles);
        $unused = null;
        try {
            $this->assertTrue($verifier->verify($manifest, $scratch, static function (): void {})->passed());
            $indexCache = $scratch.'/articles/'.hash('sha256', 'set.par2@article');
            $indexChecksum = hash_file('sha256', $indexCache);
            file_put_contents($scratch.'/data/movie.mkv', str_repeat('wrong-data', 64));
            [$offline, $unused] = $this->verifier([]);
            $result = $offline->verify($manifest, $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertSame($bytes, file_get_contents($scratch.'/data/movie.mkv'));
            $this->assertSame($indexChecksum, hash_file('sha256', $indexCache));
            $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', $bytes);
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
            if ($unused !== null) {
                $this->removeDirectory($unused);
            }
        }
    }

    public function test_derived_cleanup_is_batched_preserves_raw_cache_and_does_not_follow_symlinks(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        mkdir($scratch.'/articles');
        $cachePath = $scratch.'/articles/'.hash('sha256', 'a@b');
        $raw = (new YencService)->encode('hello', 'movie.mkv');
        file_put_contents($cachePath, $raw);
        mkdir($scratch.'/data');
        mkdir($scratch.'/data/old');
        for ($entry = 0; $entry < 600; $entry++) {
            file_put_contents($scratch.'/data/old/item-'.$entry, 'old bytes');
        }
        mkdir($scratch.'/outside');
        file_put_contents($scratch.'/outside/retained', 'preserve outside');
        symlink($scratch.'/outside', $scratch.'/data/stale-link');
        $beats = 0;
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function () use (&$beats): void {
                $beats++;
            });
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertDirectoryDoesNotExist($scratch.'/data/old');
            $this->assertFileDoesNotExist($scratch.'/data/stale-link');
            $this->assertSame('preserve outside', file_get_contents($scratch.'/outside/retained'));
            $this->assertSame($raw, file_get_contents($cachePath));
            $this->assertGreaterThanOrEqual(4, $beats);
            $this->assertLessThan(30, $beats, 'Derived cleanup must not renew the DB lease once per entry.');
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_lost_lease_during_derived_cleanup_preserves_cache_and_stops_proof_until_retry(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        mkdir($scratch.'/articles');
        $cachePath = $scratch.'/articles/'.hash('sha256', 'a@b');
        $raw = (new YencService)->encode('hello', 'movie.mkv');
        file_put_contents($cachePath, $raw);
        mkdir($scratch.'/data');
        for ($entry = 0; $entry < 600; $entry++) {
            file_put_contents($scratch.'/data/item-'.$entry, 'old bytes');
        }
        $beats = 0;
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function () use (&$beats): void {
                if (++$beats === 2) {
                    throw new \RuntimeException('Lease lost during derived cleanup.');
                }
            });
            $this->assertSame('unknown', $result->verdict);
            $this->assertSame('Lease lost during derived cleanup.', $result->reason);
            $this->assertSame($raw, file_get_contents($cachePath));
            $this->assertFileDoesNotExist($scratch.'/data/movie.mkv');
            $this->assertTrue($verifier->verify($this->manifest(1), $scratch, static function (): void {})->passed());
            $this->assertSame(['movie.mkv'], array_values(array_diff(scandir($scratch.'/data'), ['.', '..'])));
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_symlinked_derived_directory_cannot_delete_raw_article_cache(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        mkdir($scratch.'/articles');
        $path = $scratch.'/articles/'.hash('sha256', 'a@b');
        file_put_contents($path, (new YencService)->encode('hello', 'movie.mkv'));
        symlink($scratch.'/articles', $scratch.'/data');
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertSame('unknown', $result->verdict);
            $this->assertSame('Derived payload directory cannot be a symlink.', $result->reason);
            $this->assertFileExists($path);
        } finally {
            unlink($scratch.'/data');
            $this->removeDirectory($scratch);
        }
    }

    public function test_partial_source_ranges_do_not_reuse_uncovered_old_bytes(): void
    {
        [$verifier, $scratch] = $this->verifier(['tail@article' => YencArticles::part('tail', 'movie.mkv', 8, 2, 5, 2)]);
        mkdir($scratch.'/data');
        file_put_contents($scratch.'/data/movie.mkv', 'old-tail');
        try {
            $manifest = ['expected_files' => 1, 'files' => [['key' => 'source', 'subject' => 'movie.mkv', 'totalparts' => 2,
                'segments' => [['number' => 2, 'messageid' => 'tail@article', 'bytes' => 4]]]]];
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            $this->assertFalse($result->passed());
            $this->assertSame("\0\0\0\0tail", file_get_contents($scratch.'/data/movie.mkv'));
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_actual_par2_reconstructs_a_missing_file_before_passing(): void
    {
        $dir = sys_get_temp_dir().'/par2-fixture-'.bin2hex(random_bytes(8));
        mkdir($dir);
        file_put_contents($dir.'/movie.mkv', str_repeat('movie-data', 64));
        $process = new Process(['par2', 'c', '-q', '-t1', '-s64', '-r100', '-n1', '--', 'set.par2', 'movie.mkv'], $dir);
        $process->mustRun();
        $articles = [];
        $files = [['key' => 'movie', 'subject' => 'movie.mkv', 'totalparts' => 1,
            'segments' => [['number' => 1, 'messageid' => 'missing@article', 'bytes' => 640]]]];
        foreach (glob($dir.'/*.par2') as $path) {
            $name = basename($path);
            $id = $name.'@article';
            $articles[$id] = (new YencService)->encode(file_get_contents($path), $name);
            $files[] = ['key' => $name, 'subject' => $name, 'totalparts' => 1,
                'segments' => [['number' => 1, 'messageid' => $id, 'bytes' => filesize($path)]]];
        }
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertContains(['name' => 'movie.mkv', 'source' => 'par2'], $result->manifest['identity_hints']);
            $this->assertContains('movie.mkv', $result->manifest['verified_file_names']);
            $this->assertSame(count($result->manifest['verified_file_names']), $result->manifest['verified_file_count']);
            $this->assertSame(str_repeat('movie-data', 64), file_get_contents($scratch.'/data/movie.mkv'));
            $this->assertSame([], $result->manifest['files'][0]['segments']);
        } finally {
            $this->removeDirectory($dir);
            $this->removeDirectory($scratch);
        }
    }

    public function test_partial_redundant_recovery_volume_is_retained_as_available_repair_input(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'set');
        $volume = glob($fixture.'/*.vol*.par2')[0];
        $bytes = file_get_contents($volume);
        $partialName = 'set.vol99+1.par2';
        $articles['partial@article'] = YencArticles::part(substr($bytes, 0, -1), $partialName, strlen($bytes), 1, 1, 2);
        $files[] = ['key' => 'partial', 'subject' => $partialName, 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'partial@article', 'bytes' => strlen($bytes) - 1],
                ['number' => 2, 'messageid' => 'missing-parity@article', 'bytes' => 1]]];
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertContains($partialName, array_column($result->manifest['files'], 'subject'));
            $this->assertSame(count($files), $result->manifest['expected_files']);
            $this->assertNotContains($partialName, array_column($result->manifest['identity_hints'], 'name'));
            $this->assertNotContains($partialName, $result->manifest['verified_file_names']);
            $this->assertSame(str_repeat('movie-data', 64), file_get_contents($scratch.'/data/movie.mkv'));
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_par2_inventory_can_prove_a_whole_source_missing_from_observed_headers(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'set');
        $expected = count($files);
        $files = array_slice($files, 1);
        foreach ($files as $index => &$file) {
            $file['subject'] = '['.($index + 2).'/'.$expected.'] - "'.$file['subject'].'"';
        }
        unset($file);
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => $expected, 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertContains(['name' => 'movie.mkv', 'source' => 'par2'], $result->manifest['identity_hints']);
            $this->assertContains('movie.mkv', $result->manifest['verified_file_names']);
            $this->assertSame(count($result->manifest['verified_file_names']), $result->manifest['verified_file_count']);
            $this->assertCount(count($files), $result->manifest['files']);
            $this->assertSame(str_repeat('movie-data', 64), file_get_contents($scratch.'/data/movie.mkv'));
            $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', str_repeat('movie-data', 64));
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_inferred_par2_sources_cannot_cover_a_larger_unproved_declared_inventory(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'set');
        $expected = count($files) + 1;
        $files = array_slice($files, 1);
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => $expected, 'files' => $files], $scratch, static function (): void {});
            $this->assertFalse($result->passed());
            $this->assertStringContainsString('inventory', $result->reason);
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_partial_recovery_articles_required_for_repair_remain_in_published_nzb(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'set');
        $volume = glob($fixture.'/*.vol*.par2')[0];
        $name = basename($volume);
        $bytes = file_get_contents($volume);
        unset($articles[$name.'@article']);
        $files = array_values(array_filter($files, static fn (array $file): bool => $file['subject'] !== $name));
        $articles['partial@article'] = YencArticles::part(substr($bytes, 0, -1), $name, strlen($bytes), 1, 1, 2);
        $files[] = ['key' => 'partial', 'subject' => $name, 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'partial@article', 'bytes' => strlen($bytes) - 1],
                ['number' => 2, 'messageid' => 'missing-parity@article', 'bytes' => 1]]];
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
            $this->assertSame(640 + filesize($fixture.'/set.par2') + strlen($bytes) - 1, $result->manifest['verified_bytes']);
            $this->assertPublishedArticlesCanRepair($result->manifest, $articles, 'movie.mkv', str_repeat('movie-data', 64));
            $this->assertContains('partial@article', array_merge(...array_map(static fn (array $file): array => array_column($file['segments'], 'messageid'), $result->manifest['files'])));
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $articles
     */
    private function assertPublishedArticlesCanRepair(array $manifest, array $articles, string $sourceName, string $sourceBytes): void
    {
        $manifest += ['fromname' => 'fixture', 'date' => '2026-10-11 12:00:00', 'groups' => ['alt.test']];
        $writer = (new \ReflectionClass(VerifiedNzbWriter::class))->newInstanceWithoutConstructor();
        $xml = (new \ReflectionMethod($writer, 'document'))->invoke($writer, $manifest);
        $parsed = (new NzbManifestReader)->read($xml, (object) ['searchname' => 'fixture', 'fromname' => 'fixture',
            'postdate' => '2026-10-11 12:00:00', 'groups_id' => 1]);
        $publishedArticles = [];
        foreach ($parsed['files'] as $file) {
            foreach ($file['segments'] as $segment) {
                $this->assertArrayHasKey($segment['messageid'], $articles, 'Publication must never invent article IDs.');
                $publishedArticles[$segment['messageid']] = $articles[$segment['messageid']];
            }
        }
        [$verifier, $scratch] = $this->verifier($publishedArticles);
        try {
            $result = $verifier->verify($parsed, $scratch, static function (): void {});
            $this->assertTrue($result->passed(), 'Published NZB must independently reproduce repair: '.$result->reason);
            $this->assertSame($sourceBytes, file_get_contents($scratch.'/data/'.$sourceName));
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_verified_full_inventory_preserves_numeric_filenames_as_strings(): void
    {
        [$verifier, $scratch] = $this->verifier(['a@b' => (new YencService)->encode('hello', '1234')]);
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertSame(['1234'], $result->manifest['verified_file_names']);
            $this->assertSame(1, $result->manifest['verified_file_count']);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_scratch_size_walk_renews_lease_periodically_without_per_entry_callbacks(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        for ($group = 0; $group < 3; $group++) {
            mkdir($scratch.'/group-'.$group);
            for ($entry = 0; $entry < 200; $entry++) {
                file_put_contents($scratch.'/group-'.$group.'/item-'.$entry, 'abc');
            }
        }
        $beats = 0;
        try {
            $bytes = (new \ReflectionMethod($verifier, 'directoryBytes'))->invoke($verifier, $scratch, static function () use (&$beats): void {
                $beats++;
            });
            $this->assertSame(1800, $bytes);
            $this->assertGreaterThanOrEqual(4, $beats);
            $this->assertLessThan(20, $beats, 'Directory walks must not renew the DB lease once per entry.');
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_scratch_size_walk_propagates_a_lost_lease_mid_scan(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        for ($entry = 0; $entry < 600; $entry++) {
            file_put_contents($scratch.'/item-'.$entry, 'abc');
        }
        $beats = 0;
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Lease lost during scratch scan.');
            (new \ReflectionMethod($verifier, 'directoryBytes'))->invoke($verifier, $scratch, static function () use (&$beats): void {
                if (++$beats === 2) {
                    throw new \RuntimeException('Lease lost during scratch scan.');
                }
            });
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_initial_scratch_size_walk_renews_before_first_article_fetch(): void
    {
        [$unused, $scratch] = $this->verifier([]);
        $beats = 0;
        $source = new class(static function () use (&$beats): int {
            return $beats;
        }) implements ArticleSource {

            public int $beatsBeforeFetch = 0;

            public function __construct(private \Closure $counter) {}

            public function fetch(string $messageId): string
            {
                $this->beatsBeforeFetch = ($this->counter)();

                return (new YencService)->encode('hello', 'movie.mkv');
            }
        };
        $verifier = new PayloadVerifier($source, ['scratch_bytes' => 1048576, 'free_bytes' => 0, 'article_bytes' => 16777216,
            'bytes_per_second' => 0, 'par2_binary' => 'par2']);
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function () use (&$beats): void {
                $beats++;
            });
            $this->assertTrue($result->passed(), $result->reason);
            $this->assertGreaterThanOrEqual(5, $source->beatsBeforeFetch);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_lost_lease_in_initial_scratch_walk_stops_before_article_fetch(): void
    {
        [$unused, $scratch] = $this->verifier([]);
        $source = new class implements ArticleSource
        {
            public int $fetches = 0;

            public function fetch(string $messageId): string
            {
                $this->fetches++;

                return (new YencService)->encode('hello', 'movie.mkv');
            }
        };
        $verifier = new PayloadVerifier($source, ['scratch_bytes' => 1048576, 'free_bytes' => 0, 'article_bytes' => 16777216,
            'bytes_per_second' => 0, 'par2_binary' => 'par2']);
        $beats = 0;
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function () use (&$beats): void {
                if (++$beats === 3) {
                    throw new \RuntimeException('Lease lost in own scratch walk.');
                }
            });
            $this->assertFalse($result->passed());
            $this->assertSame('Lease lost in own scratch walk.', $result->reason);
            $this->assertSame(0, $source->fetches);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_checksum_pass_renews_lease_between_bounded_reads(): void
    {
        [$verifier, $scratch] = $this->verifier([]);
        $path = $scratch.'/large-source';
        file_put_contents($path, str_repeat('x', 3 * 1048576));
        $beats = 0;
        try {
            $checksum = (new \ReflectionMethod($verifier, 'checksum'))->invoke($verifier, $path, 'md5', static function () use (&$beats): void {
                $beats++;
            });
            $this->assertSame(md5(str_repeat('x', 3 * 1048576)), $checksum);
            $this->assertGreaterThanOrEqual(3, $beats);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_foreign_creator_packet_cannot_authorize_omitting_foreign_recovery(): void
    {
        [$articles, $files, $primary] = $this->par2Upload('movie.mkv', str_repeat('movie-data', 64), 'primary');
        [$unused, $unusedFiles, $foreign] = $this->par2Upload('other.bin', str_repeat('other-data', 64), 'foreign');
        $parser = new Par2Info;
        $this->assertTrue($parser->open($foreign.'/foreign.par2'));
        $foreignId = hex2bin($parser->getPackets(true)[0]['head_set_id']);
        $body = $foreignId.Par2Info::PACKET_CREATOR."foreign creator\0";
        $creator = "PAR2\0PKT".pack('VV', 32 + strlen($body), 0).md5($body, true).$body;
        $articles['primary.par2@article'] = (new YencService)->encode(file_get_contents($primary.'/primary.par2').$creator, 'primary.par2');
        $bytes = file_get_contents(glob($foreign.'/*.vol*.par2')[0]);
        $articles['partial@article'] = YencArticles::part(substr($bytes, 0, -1), 'primary.vol99+1.par2', strlen($bytes), 1, 1, 2);
        $files[] = ['key' => 'partial', 'subject' => 'primary.vol99+1.par2', 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'partial@article', 'bytes' => strlen($bytes) - 1],
                ['number' => 2, 'messageid' => 'missing-parity@article', 'bytes' => 1]]];
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertFalse($result->passed(), $result->reason);
        } finally {
            $this->removeDirectory($primary);
            $this->removeDirectory($foreign);
            $this->removeDirectory($scratch);
        }
    }

    public function test_multipart_whole_file_checksum_is_verified(): void
    {
        $raw = (new YencService)->encode('hello', 'movie.mkv');
        $raw = str_replace('=ybegin line=', '=ybegin part=1 total=1 line=', $raw);
        $raw = str_replace("name=movie.mkv\r\n", "name=movie.mkv\r\n=ypart begin=1 end=5\r\n", $raw);
        $raw = str_replace('=yend size=5 crc32=3610a686', '=yend size=5 part=1 pcrc32=3610a686 crc32=00000000', $raw);
        [$verifier, $scratch] = $this->verifier(['a@b' => $raw]);
        try {
            $result = $verifier->verify($this->manifest(1), $scratch, static function (): void {});
            $this->assertFalse($result->passed());
            $this->assertStringContainsString('Whole-file', $result->reason);
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_unrelated_par2_cannot_replace_a_missing_required_file(): void
    {
        $dir = sys_get_temp_dir().'/par2-unrelated-'.bin2hex(random_bytes(8));
        mkdir($dir);
        file_put_contents($dir.'/other.bin', str_repeat('other', 64));
        (new Process(['par2', 'c', '-q', '-t1', '-s64', '-r100', '-n1', '--', 'set.par2', 'other.bin'], $dir))->mustRun();
        $articles = [];
        $files = [['key' => 'required', 'subject' => 'required.bin', 'totalparts' => 1, 'segments' => [['number' => 1, 'messageid' => 'missing@article', 'bytes' => 320]]]];
        foreach (glob($dir.'/*.par2') as $path) {
            $name = basename($path);
            $id = $name.'@article';
            $articles[$id] = (new YencService)->encode(file_get_contents($path), $name);
            $files[] = ['key' => $name, 'subject' => $name, 'totalparts' => 1, 'segments' => [['number' => 1, 'messageid' => $id, 'bytes' => filesize($path)]]];
        }
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $this->assertFalse($verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {})->passed());
        } finally {
            $this->removeDirectory($dir);
            $this->removeDirectory($scratch);
        }
    }

    public function test_retained_other_candidate_counts_against_scratch_capacity(): void
    {
        [$verifier, $scratch] = $this->verifier(['a@b' => (new YencService)->encode('hello', 'movie.mkv')]);
        mkdir(dirname($scratch).'/old');
        file_put_contents(dirname($scratch).'/old/retained.bin', str_repeat('x', 1048576));
        try {
            $this->assertFalse($verifier->verify($this->manifest(1), $scratch, static function (): void {})->passed());
        } finally {
            $this->removeDirectory(dirname($scratch));
        }
    }

    public function test_declared_missing_segment_requires_par2_proof_even_if_bytes_cover_file(): void
    {
        [$verifier, $scratch] = $this->verifier(['a@b' => (new YencService)->encode('hello', 'movie.mkv')]);
        $manifest = $this->manifest(1);
        $manifest['files'][0]['totalparts'] = 2;
        try {
            $this->assertFalse($verifier->verify($manifest, $scratch, static function (): void {})->passed());
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    public function test_unsupported_encoding_stays_unknown_even_when_par2_cannot_repair(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.bin', str_repeat('A', 256), 'set-a');
        foreach (array_keys($articles) as $id) {
            if (str_contains($id, '.vol')) {
                unset($articles[$id]);
            }
        }
        $articles['source@article'] = 'unsupported plain text';
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('unknown', $result->verdict);
            $this->assertStringContainsString('Unsupported', $result->reason);
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_corrupt_source_checksum_can_be_repaired_by_actual_par2(): void
    {
        [$articles, $files, $fixture] = $this->par2Upload('movie.bin', str_repeat('A', 256), 'set-a');
        $articles['source@article'] = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', (new YencService)->encode(str_repeat('A', 256), 'movie.bin'));
        [$verifier, $scratch] = $this->verifier($articles);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertSame('repaired', $result->verdict, $result->reason);
        } finally {
            $this->removeDirectory($fixture);
            $this->removeDirectory($scratch);
        }
    }

    public function test_conflicting_par2_sets_cannot_replace_previously_verified_bytes(): void
    {
        [$articlesA, $filesA, $fixtureA] = $this->par2Upload('movie.bin', str_repeat('A', 256), 'set-a');
        [$articlesB, $filesB, $fixtureB] = $this->par2Upload('movie.bin', str_repeat('B', 256), 'set-b');
        $files = array_merge($filesA, array_slice($filesB, 1));
        [$verifier, $scratch] = $this->verifier($articlesA + $articlesB);
        try {
            $result = $verifier->verify(['expected_files' => count($files), 'files' => $files], $scratch, static function (): void {});
            $this->assertFalse($result->passed());
            $this->assertStringContainsString('Conflicting PAR2', $result->reason);
        } finally {
            $this->removeDirectory($fixtureA);
            $this->removeDirectory($fixtureB);
            $this->removeDirectory($scratch);
        }
    }

    /** @return array{array<string, string>, list<array<string, mixed>>, string} */
    private function par2Upload(string $name, string $bytes, string $set): array
    {
        $directory = sys_get_temp_dir().'/par2-test-'.bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory.'/'.$name, $bytes);
        (new Process(['par2', 'c', '-q', '-t1', '-s64', '-r100', '-n1', '--', $set.'.par2', $name], $directory))->mustRun();
        $articles = [];
        $files = [['key' => 'source', 'subject' => $name, 'totalparts' => 1, 'segments' => [['number' => 1, 'messageid' => 'source@article', 'bytes' => strlen($bytes)]]]];
        foreach (glob($directory.'/*.par2') as $path) {
            $filename = basename($path);
            $id = $filename.'@article';
            $articles[$id] = (new YencService)->encode(file_get_contents($path), $filename);
            $files[] = ['key' => $filename, 'subject' => $filename, 'totalparts' => 1, 'segments' => [['number' => 1, 'messageid' => $id, 'bytes' => filesize($path)]]];
        }

        return [$articles, $files, $directory];
    }

    public function test_capacity_limit_does_not_turn_into_a_success(): void
    {
        [$unused, $scratch] = $this->verifier([]);
        $source = new class implements ArticleSource
        {
            public function fetch(string $messageId): string
            {
                return (new YencService)->encode(str_repeat('x', 10000), 'movie.mkv');
            }
        };
        $verifier = new PayloadVerifier($source,
            ['scratch_bytes' => 100, 'free_bytes' => 0, 'article_bytes' => 16777216, 'bytes_per_second' => 0, 'par2_binary' => 'par2']);
        try {
            $this->assertFalse($verifier->verify($this->manifest(1), $scratch, static function (): void {})->passed());
        } finally {
            $this->removeDirectory($scratch);
        }
    }

    /** @return array<string, mixed> */
    private function manifest(int $expected): array
    {
        return ['expected_files' => $expected, 'files' => [['key' => '1', 'subject' => 'movie.mkv', 'totalparts' => 1,
            'segments' => [['number' => 1, 'messageid' => 'a@b', 'bytes' => 5]]]]];
    }

    /** @param array<string, string> $articles
     * @return array{0: PayloadVerifier, 1: string}
     */
    private function verifier(array $articles): array
    {
        $source = new class($articles) implements ArticleSource
        {
            /** @param array<string, string> $articles */
            public function __construct(private array $articles) {}

            public function fetch(string $messageId): string
            {
                return $this->articles[$messageId] ?? throw new \RuntimeException('Unavailable');
            }
        };
        $dir = sys_get_temp_dir().'/verify-'.bin2hex(random_bytes(8)).'/candidate';
        mkdir($dir, 0700, true);

        return [new PayloadVerifier($source,
            ['scratch_bytes' => 1048576, 'free_bytes' => 0, 'article_bytes' => 16777216,
                'bytes_per_second' => 0, 'par2_binary' => 'par2']), $dir];
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }

    public function test_missing_checksum_cannot_prove_integrity(): void
    {
        $this->expectException(\RuntimeException::class);
        VerifiedArticle::decode((new YencService)->encode('hello', 'movie.mkv', 128, false));
    }

    public function test_unsafe_filename_is_never_written_to_disk(): void
    {
        $this->expectException(\RuntimeException::class);
        VerifiedArticle::decode((new YencService)->encode('hello', '../movie.mkv'));
    }

    public function test_corrupt_bytes_cannot_pass(): void
    {
        $this->expectException(\RuntimeException::class);
        VerifiedArticle::decode(str_replace('crc32=3610a686', 'crc32=00000000', (new YencService)->encode('hello', 'movie.mkv')));
    }
}
