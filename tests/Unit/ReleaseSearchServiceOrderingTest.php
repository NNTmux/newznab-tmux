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
}
