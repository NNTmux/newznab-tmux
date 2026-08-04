<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\NameFixingQueryService;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class NameFixingQueryServiceTest extends TestCase
{
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
