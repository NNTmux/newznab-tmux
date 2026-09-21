<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReleaseBrowseSearchCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app['cache']->setDefaultDriver('array');
        Cache::flush();
    }

    #[Test]
    public function it_reuses_the_search_result_when_excluded_categories_have_equivalent_ordering(): void
    {
        Search::shouldReceive('isAvailable')->twice()->andReturnTrue();
        Search::shouldReceive('searchReleasesFiltered')
            ->once()
            ->withArgs(function (array $criteria, int $limit, int $offset): bool {
                return $criteria['excluded_category_ids'] === [2030, 2020]
                    && $limit === 100
                    && $offset === 0;
            })
            ->andReturn($this->searchResult(42));

        $service = new ReleaseBrowseService;

        $first = $service->getBrowseRangeForApi(1, [], 0, 100, 'posted_desc', -1, [2030, 2020]);
        $second = $service->getBrowseRangeForApi(1, [], 0, 100, 'posted_desc', -1, [2020, 2030]);

        $this->assertSame(42, $first[0]->id);
        $this->assertSame(42, $second[0]->id);
    }

    #[Test]
    public function it_keeps_different_offsets_in_separate_search_cache_entries(): void
    {
        Search::shouldReceive('isAvailable')->twice()->andReturnTrue();
        Search::shouldReceive('searchReleasesFiltered')
            ->once()
            ->withArgs(fn (array $criteria, int $limit, int $offset): bool => $limit === 100 && $offset === 0)
            ->andReturn($this->searchResult(42));
        Search::shouldReceive('searchReleasesFiltered')
            ->once()
            ->withArgs(fn (array $criteria, int $limit, int $offset): bool => $limit === 100 && $offset === 100)
            ->andReturn($this->searchResult(84));

        $service = new ReleaseBrowseService;

        $firstPage = $service->getBrowseRangeForApi(1, [], 0, 100, 'posted_desc');
        $secondPage = $service->getBrowseRangeForApi(2, [], 100, 100, 'posted_desc');

        $this->assertSame(42, $firstPage[0]->id);
        $this->assertSame(84, $secondPage[0]->id);
    }

    /**
     * @return array{ids: list<int>, documents: list<array<string, mixed>>, total: int}
     */
    private function searchResult(int $id): array
    {
        return [
            'ids' => [$id],
            'documents' => [[
                'id' => $id,
                'searchname' => 'Example release '.$id,
                'postdate_ts' => 1_700_000_000,
                'adddate_ts' => 1_700_000_100,
            ]],
            'total' => 1,
        ];
    }
}
