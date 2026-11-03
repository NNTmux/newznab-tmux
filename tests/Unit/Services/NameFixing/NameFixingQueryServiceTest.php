<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Models\Category;
use App\Services\NameFixing\NameFixingQueryService;
use App\Services\Search\Contracts\SearchServiceInterface;
use App\Services\Search\DTO\NameFixingSearchQuery;
use App\Services\Search\DTO\NameFixingSearchResult;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class NameFixingQueryServiceTest extends TestCase
{
    public function test_crc_donor_queries_keep_file_sizes_and_normalize_legacy_hash_keys(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())->method('select')
            ->willReturnCallback(function (string $sql, array $bindings): array {
                $this->assertContains('00000ABC', $bindings);
                $this->assertContains('00abc', $bindings);
                $this->assertNotContains('not-a-hash', $bindings);
                $this->assertStringContainsString('FROM release_files rf', $sql);
                $this->assertStringContainsString('rf.size AS filesize', $sql);

                return [(object) ['match_key' => 'abc', 'filesize' => 123, 'predb_id' => 42]];
            });
        $service = new NameFixingQueryService($database);

        $donors = $service->crcDonors(['00000abc', 'not-a-hash']);

        $this->assertSame(123, $donors['00000ABC'][0]->filesize);
    }

    public function test_invalid_crc_lookup_values_do_not_query_the_database(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->never())->method('select');
        $service = new NameFixingQueryService($database);

        $this->assertSame([], $service->crcDonors(['invalid', '123456789']));
    }

    public function test_indexed_candidates_are_confirmed_by_the_database(): void
    {
        $search = $this->createMock(SearchServiceInterface::class);
        $search->expects($this->once())
            ->method('searchNameFixingCandidates')
            ->with($this->callback(static fn (NameFixingSearchQuery $query): bool => $query->source === NameFixingSearchQuery::SOURCE_FILES
                && $query->afterId === 5
                && $query->normalizedCategoryIds() !== []))
            ->willReturn(new NameFixingSearchResult(
                ids: [11, 22],
                documents: [11 => ['id' => 11], 22 => ['id' => 22]],
                total: 2,
                available: true,
                hasMore: false,
                driver: 'manticore',
            ));

        $rows = [(object) ['releases_id' => 11], (object) ['releases_id' => 22]];
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'r.id IN (?,?)')
                    && str_contains($sql, 'EXISTS (SELECT 1 FROM release_files')),
                $this->callback(static fn (array $bindings): bool => array_slice($bindings, -4) === [11, 22, 5, 2])
            )
            ->willReturn($rows);

        $service = new NameFixingQueryService($database, $search);

        $this->assertSame($rows, $service->candidateBatch(NameFixingQueryService::SOURCE_FILES, 2, 1, 5, 2));
    }

    public function test_unavailable_index_falls_back_to_the_original_candidate_query(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchNameFixingCandidates')->willReturn(NameFixingSearchResult::unavailable('elasticsearch'));

        $row = (object) ['releases_id' => 42];
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => ! str_contains($sql, 'r.id IN (')
                    && str_contains($sql, 'EXISTS (SELECT 1 FROM release_nfos')),
                [10, 20, 0, 1]
            )
            ->willReturn([$row]);

        $service = new NameFixingQueryService($database, $search);

        $this->assertSame([$row], $service->candidateBatch(NameFixingQueryService::SOURCE_NFO, 2, 2, 0, 1));
    }

    public function test_partial_index_results_are_filled_without_duplicate_rows(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchNameFixingCandidates')->willReturn(new NameFixingSearchResult(
            ids: [11],
            documents: [11 => ['id' => 11]],
            total: 1,
            available: true,
            hasMore: false,
            driver: 'elasticsearch',
        ));

        $first = (object) ['releases_id' => 11];
        $second = (object) ['releases_id' => 22];
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls([$first], [$first, $second]);

        $service = new NameFixingQueryService($database, $search);

        $this->assertSame(
            [$first, $second],
            $service->candidateBatch(NameFixingQueryService::SOURCE_FILES, 2, 2, 0, 2)
        );
    }

    public function test_media_rows_use_complete_indexed_documents_without_database_hydration(): void
    {
        $search = $this->createMock(SearchServiceInterface::class);
        $search->expects($this->once())
            ->method('searchNameFixingCandidates')
            ->with($this->callback(static fn (NameFixingSearchQuery $query): bool => $query->source === NameFixingSearchQuery::SOURCE_MEDIA
                && $query->normalizedReleaseIds() === [11]))
            ->willReturn(new NameFixingSearchResult(
                ids: [11],
                documents: [11 => [
                    'id' => 11,
                    'has_media_info' => 1,
                    'media_unique_id' => 'uid-11',
                    'media_movie_name' => 'Movie.Name.2026-GROUP',
                    'media_file_name' => 'movie.mkv',
                ]],
                total: 1,
                available: true,
                hasMore: false,
                driver: 'manticore',
            ));

        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->never())->method('select');
        $service = new NameFixingQueryService($database, $search);

        $this->assertEquals([
            (object) [
                'releases_id' => 11,
                'uid' => 'uid-11',
                'movie_name' => 'Movie.Name.2026-GROUP',
                'file_name' => 'movie.mkv',
            ],
        ], $service->mediaRows([11]));
    }

    public function test_uid_donors_use_indexed_match_keys_and_database_eligibility(): void
    {
        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchNameFixingCandidates')->willReturn(new NameFixingSearchResult(
            ids: [11, 22],
            documents: [
                11 => ['id' => 11, 'media_unique_id' => 'uid-a'],
                22 => ['id' => 22, 'media_unique_id' => 'uid-b'],
            ],
            total: 2,
            available: true,
            hasMore: false,
            driver: 'elasticsearch',
        ));

        $first = (object) ['releases_id' => 11, 'relsize' => 100, 'searchname' => 'A', 'fromname' => 'poster', 'predb_id' => 1];
        $second = (object) ['releases_id' => 22, 'relsize' => 200, 'searchname' => 'B', 'fromname' => 'poster', 'predb_id' => 2];
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())->method('select')->willReturn([$first, $second]);

        $service = new NameFixingQueryService($database, $search);
        $donors = $service->uidDonors(['uid-a', 'uid-b']);

        $this->assertSame([$first], $donors['uid-a']);
        $this->assertSame([$second], $donors['uid-b']);
        $this->assertSame('uid-a', $first->match_key);
        $this->assertSame('uid-b', $second->match_key);
    }

    public function test_it_preserves_every_source_row_when_grouping_by_release(): void
    {
        $database = $this->createStub(ConnectionInterface::class);
        $service = new NameFixingQueryService($database);
        $first = (object) ['releases_id' => 10, 'textstring' => 'first.rar'];
        $second = (object) ['releases_id' => 10, 'textstring' => 'second.rar'];
        $third = (object) ['releases_id' => 20, 'textstring' => 'third.rar'];

        $grouped = $service->groupByReleaseId([$first, $second, $third]);

        $this->assertSame([$first, $second], $grouped[10]);
        $this->assertSame([$third], $grouped[20]);
    }

    public function test_file_candidates_use_exists_without_lossy_grouping(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'EXISTS (SELECT 1 FROM release_files')
                    && ! str_contains($sql, 'GROUP BY')),
                $this->callback(static fn (array $bindings): bool => $bindings[array_key_last($bindings)] === 100)
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->candidateBatch(NameFixingQueryService::SOURCE_FILES, 2, 2, 0, 100);
    }

    public function test_other_sources_do_not_wait_for_additional_processing(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with($this->callback(static fn (string $sql): bool => ! str_contains($sql, 'passwordstatus')))
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->candidateBatch(NameFixingQueryService::SOURCE_HASH, 1, 1, 0, 100);
    }

    public function test_uid_candidates_only_use_media_infos(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'FROM media_infos source_media')
                    && str_contains($sql, 'source_media.unique_id IS NOT NULL')
                    && ! str_contains($sql, 'release_unique')),
                [0, 100]
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->candidateBatch(NameFixingQueryService::SOURCE_UID, 2, 3, 0, 100);
    }

    public function test_media_rows_only_load_from_media_infos(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'FROM media_infos mi')
                    && str_contains($sql, 'mi.releases_id IN (?,?)')
                    && ! str_contains($sql, 'release_unique')
                    && ! str_contains($sql, 'UNION ALL')),
                [11, 22]
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->mediaRows([11, 22]);
    }

    public function test_uid_donors_only_load_from_media_infos(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'FROM media_infos mi')
                    && str_contains($sql, 'mi.unique_id IN (?,?)')
                    && ! str_contains($sql, 'release_unique')
                    && ! str_contains($sql, 'UNION ALL')),
                ['first-uid', 'second-uid', 'nonscene@Ef.net (EF)']
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);

        $this->assertSame([], $service->uidDonors(['first-uid', 'second-uid']));
    }

    public function test_xxx_candidates_include_compact_searchnames_and_xxx_sd_categories(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, "r.searchname LIKE 'SDX264XXX-%'")
                    && str_contains($sql, "source_xxx.name LIKE '%SDX264XXX-%'")
                    && str_contains($sql, (string) Category::XXX_SD)
                    && str_contains($sql, '6042')
                    && str_contains($sql, '6080')),
                $this->callback(static fn (array $bindings): bool => array_slice($bindings, -2) === [0, 100])
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->candidateBatch(NameFixingQueryService::SOURCE_XXX, 2, 1, 0, 100);
    }

    public function test_xxx_file_rows_include_sdporn_and_compact_sdx_names(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, "rf.name LIKE '%SDPORN%'")
                    && str_contains($sql, "rf.name LIKE '%SDX264XXX-%'")),
                [11]
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->fileRows([11], NameFixingQueryService::SOURCE_XXX);
    }

    public function test_predb_workers_use_disjoint_modulo_partitions_without_offsets(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'MOD(p.id, ?) = ?')
                    && ! str_contains($sql, 'OFFSET')),
                $this->callback(static fn (array $bindings): bool => $bindings[1] === 4
                    && $bindings[2] === 2
                    && $bindings[3] === 250)
            )
            ->willReturn([]);

        $service = new NameFixingQueryService($database);
        $service->predbBatch(3, 4, 250);
    }
}
