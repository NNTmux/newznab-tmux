<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\NameFixingQueryService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
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
