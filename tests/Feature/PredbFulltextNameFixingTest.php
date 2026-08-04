<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\NameFixing\NameFixingQueryService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NameFixing\ReleaseUpdateService;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Tests\TestCase;

class PredbFulltextNameFixingTest extends TestCase
{
    public function test_it_attaches_predb_id_when_the_confirmed_title_is_already_correct(): void
    {
        $title = 'Valid.Scene.Release.2026-GROUP';
        Search::shouldReceive('isAvailable')->once()->andReturnTrue();
        Search::shouldReceive('searchReleases')
            ->once()
            ->with(['name' => $title, 'searchname' => $title], 21)
            ->andReturn([42]);

        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->once())
            ->method('select')
            ->willReturn([
                (object) [
                    'releases_id' => 42,
                    'name' => $title,
                    'searchname' => $title,
                    'fromname' => 'poster',
                    'groups_id' => 1,
                    'categories_id' => 7010,
                ],
            ]);

        $updates = $this->createMock(ReleaseUpdateService::class);
        $updates->expects($this->once())->method('attachPredbId')->with(42, 7);
        $service = new NameFixingService(
            updateService: $updates,
            queries: new NameFixingQueryService($database)
        );

        $matched = $service->matchPredbFulltext((object) [
            'predb_id' => 7,
            'title' => $title,
            'source' => 'srrdb',
        ]);

        $this->assertSame(1, $matched);
    }

    public function test_it_marks_sixteen_confirmed_candidates_as_a_flood(): void
    {
        $title = 'Valid.Scene.Release.2026-GROUP';
        Search::shouldReceive('isAvailable')->once()->andReturnTrue();
        Search::shouldReceive('searchReleases')->once()->andReturn(range(1, 21));

        $database = $this->createStub(ConnectionInterface::class);
        $database->method('select')->willReturn(array_map(
            static fn (int $id): object => (object) [
                'releases_id' => $id,
                'name' => $title,
                'searchname' => 'Old.Name-'.$id,
                'fromname' => 'poster',
                'groups_id' => 1,
                'categories_id' => 7010,
            ],
            range(1, 16)
        ));

        $service = new NameFixingService(
            updateService: $this->createStub(ReleaseUpdateService::class),
            queries: new NameFixingQueryService($database)
        );

        $matched = $service->matchPredbFulltext((object) [
            'predb_id' => 7,
            'title' => $title,
            'source' => 'srrdb',
        ]);

        $this->assertSame(-1, $matched);
    }

    public function test_backend_unavailability_is_not_recorded_as_a_no_match(): void
    {
        Search::shouldReceive('isAvailable')->once()->andReturnFalse();
        Search::shouldReceive('searchReleases')->never();
        $database = $this->createMock(ConnectionInterface::class);
        $database->expects($this->never())->method('select');
        $service = new NameFixingService(
            updateService: $this->createStub(ReleaseUpdateService::class),
            queries: new NameFixingQueryService($database)
        );

        $this->expectException(RuntimeException::class);
        $service->matchPredbFulltext((object) [
            'predb_id' => 7,
            'title' => 'Valid.Scene.Release.2026-GROUP',
            'source' => 'srrdb',
        ]);
    }
}
