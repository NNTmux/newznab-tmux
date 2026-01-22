<?php

namespace Tests\Unit;

use App\Services\Releases\ReleaseBrowseService;
use PHPUnit\Framework\TestCase;

class ReleaseBrowseServiceTest extends TestCase
{
    private ReleaseBrowseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReleaseBrowseService;
    }

    /**
     * Test that getBrowseOrdering returns all expected ordering options.
     */
    public function test_get_browse_ordering_returns_all_options(): void
    {
        $ordering = $this->service->getBrowseOrdering();

        $this->assertIsArray($ordering);
        $this->assertContains('name_asc', $ordering);
        $this->assertContains('name_desc', $ordering);
        $this->assertContains('cat_asc', $ordering);
        $this->assertContains('cat_desc', $ordering);
        $this->assertContains('posted_asc', $ordering);
        $this->assertContains('posted_desc', $ordering);
        $this->assertContains('added_asc', $ordering);
        $this->assertContains('added_desc', $ordering);
        $this->assertContains('size_asc', $ordering);
        $this->assertContains('size_desc', $ordering);
        $this->assertContains('files_asc', $ordering);
        $this->assertContains('files_desc', $ordering);
        $this->assertContains('stats_asc', $ordering);
        $this->assertContains('stats_desc', $ordering);
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
     * Test that getBrowseOrder handles unknown order type by defaulting to postdate.
     */
    public function test_get_browse_order_defaults_unknown_to_postdate(): void
    {
        $result = $this->service->getBrowseOrder('unknown_desc');

        $this->assertEquals(['postdate', 'desc'], $result);
    }

    /**
     * Test that getBrowseOrder handles invalid direction by defaulting to desc.
     */
    public function test_get_browse_order_defaults_invalid_direction_to_desc(): void
    {
        $result = $this->service->getBrowseOrder('name_invalid');

        $this->assertEquals(['searchname', 'desc'], $result);
    }
}
