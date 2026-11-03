<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Collection;
use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\BinariesService;
use App\Services\ForkingService;
use App\Services\NNTP\NNTPService;
use App\Services\RegexService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class CbpGroupScopedLookupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        DB::statement('CREATE TABLE usenet_groups (id INTEGER PRIMARY KEY, name VARCHAR(255), active INTEGER DEFAULT 0, backfill INTEGER DEFAULT 0)');
        DB::statement('CREATE TABLE collections (id INTEGER PRIMARY KEY, groups_id INTEGER, date DATETIME NULL, fromname VARCHAR(255) DEFAULT \'\', collectionhash VARCHAR(40) DEFAULT \'\')');
        DB::statement('CREATE TABLE binaries (id INTEGER PRIMARY KEY, collections_id INTEGER, name VARCHAR(255), totalparts INTEGER DEFAULT 1, currentparts INTEGER DEFAULT 1, binaryhash VARCHAR(32) DEFAULT \'\')');
        DB::statement('CREATE TABLE parts (binaries_id INTEGER, partnumber INTEGER, number INTEGER, messageid VARCHAR(255), size INTEGER, PRIMARY KEY (binaries_id, partnumber))');

        DB::table('usenet_groups')->insert([
            ['id' => 1, 'name' => 'alt.binaries.one', 'active' => 1, 'backfill' => 0],
            ['id' => 2, 'name' => 'alt.binaries.two', 'active' => 0, 'backfill' => 1],
            ['id' => 3, 'name' => 'alt.binaries.three', 'active' => 1, 'backfill' => 0],
            ['id' => 4, 'name' => 'alt.binaries.inactive', 'active' => 0, 'backfill' => 0],
        ]);
    }

    public function test_release_work_counts_only_active_groups_that_have_collections(): void
    {
        $this->collectionWithPart(10, groupId: 1, articleNumber: 1);
        $this->collectionWithPart(11, groupId: 1, articleNumber: 2);
        $this->collectionWithPart(20, groupId: 2, articleNumber: 3);
        $this->collectionWithPart(40, groupId: 4, articleNumber: 4);

        $this->assertSame([1 => true, 2 => true, 4 => true], Collection::groupIdsWithCollections());

        $forking = (new ReflectionClass(ForkingService::class))->newInstanceWithoutConstructor();
        $this->assertSame(2, (new ReflectionMethod(ForkingService::class, 'getReleaseWorkCount'))->invoke($forking));
    }

    public function test_postdate_ignores_the_same_article_number_in_another_group(): void
    {
        $this->collectionWithPart(10, groupId: 1, articleNumber: 500, date: '2020-01-01 00:00:00');
        $this->collectionWithPart(20, groupId: 2, articleNumber: 500, date: '2021-06-01 12:00:00');
        $binaries = new BinariesService(new BinariesConfig, nntp: $this->createMock(NNTPService::class));

        $timestamp = $binaries->postdate(500, ['group' => 'alt.binaries.two', 'first' => 500, 'last' => 500]);

        $this->assertSame(strtotime('2021-06-01 12:00:00'), $timestamp);
    }

    public function test_collection_regex_tester_only_reads_binaries_of_the_requested_group(): void
    {
        $this->collectionWithPart(10, groupId: 1, articleNumber: 1, binaryName: 'Wanted.Release.part01.rar');
        $this->collectionWithPart(20, groupId: 2, articleNumber: 2, binaryName: 'Other.Group.part01.rar');

        $results = (new RegexService('collection_regexes'))
            ->testCollectionRegex('alt.binaries.one', '/^(?P<name>.+?)\.part\d+/', 10);

        $files = [];
        foreach ($results as $binaries) {
            foreach ($binaries as $binary) {
                $files[] = $binary['file_name'];
            }
        }
        $this->assertSame(['Wanted.Release.part01.rar'], $files);
    }

    private function collectionWithPart(
        int $collectionId,
        int $groupId,
        int $articleNumber,
        string $date = '2020-01-01 00:00:00',
        ?string $binaryName = null,
    ): void {
        DB::table('collections')->insert(['id' => $collectionId, 'groups_id' => $groupId, 'date' => $date]);
        DB::table('binaries')->insert([
            'id' => $collectionId,
            'collections_id' => $collectionId,
            'name' => $binaryName ?? 'Binary.'.$collectionId,
        ]);
        DB::table('parts')->insert([
            'binaries_id' => $collectionId,
            'partnumber' => 1,
            'number' => $articleNumber,
            'messageid' => '<'.$collectionId.'@example.test>',
            'size' => 100,
        ]);
    }
}
