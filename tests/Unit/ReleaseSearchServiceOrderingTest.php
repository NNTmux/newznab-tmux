<?php

namespace Tests\Unit;

use App\Services\Releases\ReleaseSearchService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ReleaseSearchServiceOrderingTest extends TestCase
{
    private ReleaseSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Use reflection to instantiate without constructor dependencies
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
    }

    /**
     * Test that getBrowseOrder correctly maps 'added' to 'adddate' column.
     */
    public function test_get_browse_order_maps_added_to_adddate(): void
    {
        $result = $this->service->getBrowseOrder('added_desc');

        $this->assertEquals(['adddate', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly handles added ascending.
     */
    public function test_get_browse_order_handles_added_ascending(): void
    {
        $result = $this->service->getBrowseOrder('added_asc');

        $this->assertEquals(['adddate', 'asc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'posted' to 'postdate' column.
     */
    public function test_get_browse_order_maps_posted_to_postdate(): void
    {
        $result = $this->service->getBrowseOrder('posted_desc');

        $this->assertEquals(['postdate', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder defaults to postdate desc for empty string.
     */
    public function test_get_browse_order_defaults_to_postdate_desc(): void
    {
        $result = $this->service->getBrowseOrder('');

        $this->assertEquals(['postdate', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'name' to 'searchname' column.
     */
    public function test_get_browse_order_maps_name_to_searchname(): void
    {
        $result = $this->service->getBrowseOrder('name_asc');

        $this->assertEquals(['searchname', 'asc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'size' to 'size' column.
     */
    public function test_get_browse_order_maps_size_correctly(): void
    {
        $result = $this->service->getBrowseOrder('size_desc');

        $this->assertEquals(['size', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'files' to 'totalpart' column.
     */
    public function test_get_browse_order_maps_files_to_totalpart(): void
    {
        $result = $this->service->getBrowseOrder('files_asc');

        $this->assertEquals(['totalpart', 'asc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'stats' to 'grabs' column.
     */
    public function test_get_browse_order_maps_stats_to_grabs(): void
    {
        $result = $this->service->getBrowseOrder('stats_desc');

        $this->assertEquals(['grabs', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder correctly maps 'cat' to 'categories_id' column.
     */
    public function test_get_browse_order_maps_cat_to_categories_id(): void
    {
        $result = $this->service->getBrowseOrder('cat_asc');

        $this->assertEquals(['categories_id', 'asc'], $result);
    }

    /**
     * Test that the search candidate buffer fetches enough rows for page one without exploding.
     */
    public function test_determine_search_candidate_limit_buffers_first_page(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('determineSearchCandidateLimit');

        $this->assertSame(500, $method->invoke($this->service, 0, 50));
    }

    /**
     * Test that the search candidate buffer grows with the offset and caps deep pages.
     */
    public function test_determine_search_candidate_limit_caps_large_offsets(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('determineSearchCandidateLimit');

        $this->assertSame(550, $method->invoke($this->service, 50, 50));
        $this->assertSame(2000, $method->invoke($this->service, 2500, 50));
    }

    /**
     * Test that size bucket filters resolve to byte bounds used by indexed criteria.
     */
    public function test_resolve_size_range_bounds_maps_ui_buckets_to_bytes(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('resolveSizeRangeBounds');

        /** @var array{0:int,1:int} $bounds */
        $bounds = $method->invoke($this->service, 2, 4);

        $this->assertSame(209715200, $bounds[0]);
        $this->assertSame(1048576000, $bounds[1]);
    }

    /**
     * Test that postdate bounds map days-old/new inputs into sane unix timestamps.
     */
    public function test_resolve_postdate_bounds_maps_day_filters_to_timestamps(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('resolvePostdateBounds');

        /** @var array{0:int,1:int} $bounds */
        $bounds = $method->invoke($this->service, 2, 10);
        $now = time();

        $this->assertGreaterThanOrEqual($now - (10 * 86400) - 5, $bounds[0]);
        $this->assertLessThanOrEqual($now - (10 * 86400) + 5, $bounds[0]);
        $this->assertGreaterThanOrEqual($now - (2 * 86400) - 5, $bounds[1]);
        $this->assertLessThanOrEqual($now - (2 * 86400) + 5, $bounds[1]);
    }

    /**
     * Test that the paged ID query only selects release IDs from the releases table.
     */
    public function test_build_search_page_ids_sql_uses_releases_only(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('buildSearchPageIdsSql');

        $sql = $method->invoke(
            $this->service,
            'WHERE r.passwordstatus = 0 AND r.id IN (1,2,3)',
            ['postdate', 'desc'],
            50,
            0
        );

        $this->assertSame(
            'SELECT r.id FROM releases r WHERE r.passwordstatus = 0 AND r.id IN (1,2,3) ORDER BY r.postdate desc LIMIT 50 OFFSET 0',
            $sql
        );
        $this->assertStringNotContainsString('JOIN', $sql);
    }

    /**
     * Test that the lightweight count query is built directly against releases.
     */
    public function test_get_releases_count_for_where_builds_releases_only_count_query(): void
    {
        $reflection = new ReflectionClass(ReleaseSearchService::class);
        $method = $reflection->getMethod('getReleasesCountForWhere');

        $service = $this->getMockBuilder(ReleaseSearchService::class)
            ->onlyMethods(['getPagerCount'])
            ->getMock();

        $service->expects($this->once())
            ->method('getPagerCount')
            ->with('SELECT COUNT(*) as count FROM releases r WHERE r.passwordstatus = 0 AND r.id IN (1,2,3)')
            ->willReturn(3);

        $this->assertSame(3, $method->invoke($service, 'WHERE r.passwordstatus = 0 AND r.id IN (1,2,3)'));
    }
}
