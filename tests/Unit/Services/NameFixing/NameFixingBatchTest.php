<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\NameFixingQueryService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\NameFixing\Sdx264XxxNameResolver;
use App\Services\Search\Contracts\SearchServiceInterface;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NameFixingBatchTest extends TestCase
{
    #[Test]
    public function grouped_processing_fetches_sources_once_for_the_whole_batch(): void
    {
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->exactly(5))
            ->method('select')
            ->willReturnCallback(function (string $sql): array {
                $this->assertStringNotContainsString('GROUP_CONCAT', $sql);
                $this->assertStringNotContainsString('GROUP BY r.id', $sql);

                if (str_contains($sql, 'r.proc_nfo')) {
                    $this->assertStringContainsString('r.nfostatus > -1', $sql);

                    return [$this->processedRelease(10), $this->processedRelease(20)];
                }

                return [];
            });

        $serviceReflection = new ReflectionClass(NameFixingService::class);
        $service = $serviceReflection->newInstanceWithoutConstructor();
        $serviceReflection->getProperty('queries')
            ->setValue($service, new NameFixingQueryService($database));
        $serviceReflection->getProperty('updateService')
            ->setValue(
                $service,
                (new ReflectionClass(ReleaseUpdateService::class))->newInstanceWithoutConstructor()
            );

        $this->assertSame(
            ['checked' => 2, 'fixed' => 0],
            $service->processStandardBatch('a', 100, false)
        );
    }

    #[Test]
    public function xxx_processing_handles_searchname_and_filename_matches_once_per_release(): void
    {
        $compactName = 'SDX264XXX-W4B210316SAPPHIRAINTERVIEW';
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->exactly(2))
            ->method('select')
            ->willReturnCallback(static function (string $sql) use ($compactName): array {
                if (str_contains($sql, 'FROM release_files rf')) {
                    return [
                        (object) ['releases_id' => 10, 'textstring' => $compactName],
                        (object) ['releases_id' => 20, 'textstring' => $compactName],
                    ];
                }

                return [
                    (object) [
                        'releases_id' => 10,
                        'searchname' => $compactName,
                    ],
                    (object) [
                        'releases_id' => 20,
                        'searchname' => 'Unresolved.Release',
                    ],
                ];
            });

        $updates = $this->createMock(ReleaseUpdateService::class);
        $updates->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function () use ($updates): void {
                $updates->matched = false;
            });
        $updates->expects($this->exactly(2))->method('incrementChecked');

        $updatedReleaseIds = [];
        $updates->expects($this->exactly(2))
            ->method('updateRelease')
            ->willReturnCallback(function (object $release) use ($updates, &$updatedReleaseIds): void {
                $updatedReleaseIds[] = (int) $release->releases_id;
                $updates->matched = true;
            });

        $search = $this->createStub(SearchServiceInterface::class);
        $search->method('searchPredb')->willReturn([]);
        $service = new QuietNameFixingService(
            new NameFixingQueryService($database),
            $updates,
            new Sdx264XxxNameResolver($search)
        );

        $service->fixXXXNamesWithFiles(1, false, 1, true, false);

        $this->assertSame([10, 20], $updatedReleaseIds);
    }

    private function processedRelease(int $id): object
    {
        return (object) [
            'releases_id' => $id,
            'guid' => "guid-{$id}",
            'name' => "release-{$id}",
            'searchname' => "release-{$id}",
            'size' => 1000,
            'groupName' => 'alt.binaries.test',
            'categoryid' => 8010,
            'nfostatus' => 0,
            'proc_nfo' => NameFixingService::PROC_NFO_DONE,
            'proc_files' => NameFixingService::PROC_FILES_DONE,
            'proc_par2' => NameFixingService::PROC_PAR2_DONE,
            'proc_uid' => NameFixingService::PROC_UID_DONE,
            'proc_hash16k' => NameFixingService::PROC_HASH16K_DONE,
            'proc_srr' => NameFixingService::PROC_SRR_DONE,
            'proc_crc32' => NameFixingService::PROC_CRC_DONE,
        ];
    }
}

class QuietNameFixingService extends NameFixingService
{
    public function __construct(
        NameFixingQueryService $queries,
        ReleaseUpdateService $updateService,
        Sdx264XxxNameResolver $resolver
    ) {
        $this->queries = $queries;
        $this->updateService = $updateService;
        $this->sdx264XxxNameResolver = $resolver;
    }

    protected function startBatch(string $source, int $time, int $categories, string $message): bool
    {
        return true;
    }

    protected function echoStartMessage(int $time, string $type): void {}

    protected function echoFoundCount(bool $echo, string $type): void {}

    protected function echoRenamed(bool $show): void {}
}
