<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchReleaseListRequest;
use App\Models\Category;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\Contracts\SearchServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class SearchController extends BasePageController
{
    private SearchServiceInterface $searchService;

    private ReleaseSearchService $releaseSearchService;

    private ReleaseBrowseService $releaseBrowseService;

    public function __construct(
        SearchServiceInterface $searchService,
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService
    ) {
        parent::__construct();
        $this->searchService = $searchService;
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
    }

    /**
     * @throws \Exception
     */
    public function search(SearchReleaseListRequest $request): mixed
    {
        $listQuery = $request->listQuery();
        $search = (string) $listQuery['search'];
        $category = $request->categoryIds();
        $orderBy = (string) $listQuery['ob'];
        $page = $request->integer('page', 1);
        $perPage = max(1, (int) config('nntmux.items_per_page'));
        $group = $this->scalarInput($request, 'group');
        $ranges = $request->ranges();
        $searchFields = [
            'searchname' => $search,
            'name' => $this->scalarInput($request, 'searchadvsubject'),
            'fromname' => $this->scalarInput($request, 'searchadvposter'),
            'filename' => $this->scalarInput($request, 'searchadvfilename'),
        ];
        $hasText = $request->anyFilled(['search', 'searchadvsubject', 'searchadvposter', 'searchadvfilename']);
        $hasFilters = $ranges !== [] || $group !== '' || $category !== [-1] || $request->anyFilled(['minage', 'maxage']);
        $results = [];
        if ($hasText || $hasFilters) {
            $matches = $this->releaseSearchService->search(
                $searchFields,
                $group === '' ? -1 : $group,
                -1,
                -1,
                $request->filled('minage') ? $request->integer('minage') : -1,
                $request->filled('maxage') ? $request->integer('maxage') : -1,
                $this->paginationOffset($page, $perPage),
                $perPage,
                $orderBy,
                -1,
                $this->userdata->categoryexclusions ?? [],
                (string) $listQuery['search_type'],
                $category,
                0,
                ['ranges' => $ranges, 'filter_only' => ! $hasText && $hasFilters],
            );
            $results = $this->paginate($matches ?? [], $matches[0]->_totalrows ?? 0, $perPage, $page, $request->url(), $listQuery);
        }
        $orderByUrls = [];
        foreach ($this->releaseBrowseService->getBrowseOrdering() as $order) {
            $orderByUrls['orderby'.$order] = $request->url().'?'.http_build_query(array_replace($listQuery, ['ob' => $order]));
        }
        $suggestEnabled = $this->searchService->isSuggestEnabled();
        $this->viewData = array_merge($this->viewData, $orderByUrls, [
            'subject' => $this->scalarInput($request, 'subject'),
            'search' => $search,
            'id' => $this->scalarInput($request, 'id'),
            'category' => $category,
            'covgroup' => '',
            'lastvisit' => $this->userdata->lastlogin,
            'results' => $results,
            'searchSubmitted' => $hasText || $hasFilters,
            'sadvanced' => $listQuery['search_type'] === 'adv',
            'listQuery' => $listQuery,
            'catlist' => $this->getCategorySelectOptions(),
            'meta_title' => 'Search Nzbs',
            'meta_keywords' => 'search,nzb,description,details',
            'meta_description' => 'Search for Nzbs',
            'spellSuggestion' => $this->resolveSpellSuggestion($search, $results, $suggestEnabled),
            'autocompleteEnabled' => $this->searchService->isAutocompleteEnabled(),
            'suggestEnabled' => $suggestEnabled,
        ]);

        return view('search.index', $this->viewData);
    }

    /**
     * @param  array<int, mixed>|LengthAwarePaginator<int, mixed>  $results
     */
    private function resolveSpellSuggestion(string $searchQuery, array|LengthAwarePaginator $results, bool $suggestEnabled): ?string
    {
        if ($searchQuery === '' || ! $suggestEnabled || $this->resultCount($results) > 3) {
            return null;
        }

        $suggestions = $this->searchService->suggest($searchQuery);
        if ($suggestions === []) {
            return null;
        }

        usort($suggestions, static fn (array $a, array $b): int => (int) $b['docs'] <=> (int) $a['docs']);

        return $suggestions[0]['suggest'] !== $searchQuery ? $suggestions[0]['suggest'] : null;
    }

    /**
     * @param  array<int, mixed>|LengthAwarePaginator<int, mixed>  $results
     */
    private function resultCount(array|LengthAwarePaginator $results): int
    {
        if ($results instanceof LengthAwarePaginator) {
            return $results->total();
        }

        return count($results);
    }

    /**
     * @return array<int, string>
     */
    private function getCategorySelectOptions(): array
    {
        try {
            return Cache::remember('search:category-select-options', now()->addMinutes(10), fn (): array => Category::getForSelect());
        } catch (\Throwable) {
            return Category::getForSelect();
        }
    }
}
