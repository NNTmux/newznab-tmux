<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Api\ReleaseData;
use App\Exceptions\NzbUploadException;
use App\Facades\Search;
use App\Http\Controllers\BasePageController;
use App\Http\Controllers\GetNzbController;
use App\Models\Category;
use App\Models\Release;
use App\Models\User;
use App\Services\Api\ApiCapabilitiesService;
use App\Services\Api\ApiInputCanonicalizer;
use App\Services\Api\ApiQueryParameters;
use App\Services\Api\ApiReleaseRowCache;
use App\Services\Api\ApiUsageService;
use App\Services\Api\ApiUserResolver;
use App\Services\Api\V2\ApiV2Presenter;
use App\Services\Nzb\NzbUploadStagingService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Services\Search\DTO\SearchCursor;
use App\Services\Search\SearchCursorCodec;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use STS\ZipStream\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiV2Controller extends BasePageController
{
    private ReleaseSearchService $releaseSearchService;

    private ReleaseBrowseService $releaseBrowseService;

    private ApiReleaseRowCache $releaseRowCache;

    private ApiQueryParameters $queryParameters;

    private ApiUsageService $usageService;

    private ApiUserResolver $userResolver;

    private ApiV2Presenter $presenter;

    private ApiCapabilitiesService $capabilitiesService;

    private SearchCursorCodec $cursorCodec;

    private NzbUploadStagingService $nzbUploadStagingService;

    private ApiInputCanonicalizer $inputCanonicalizer;

    /** @var array{enabled: bool, offset: int, limit: int, query_hash: string, cursor: SearchCursor|null} */
    private array $paginationContext = ['enabled' => false, 'offset' => 0, 'limit' => 0, 'query_hash' => '', 'cursor' => null];

    /**
     * @var array<int, object>
     */
    private array $resolvedUserStats = [];

    public function __construct(
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService,
        ?ApiReleaseRowCache $releaseRowCache = null,
        ?ApiQueryParameters $queryParameters = null,
        ?ApiUsageService $usageService = null,
        ?ApiUserResolver $userResolver = null,
        ?ApiV2Presenter $presenter = null,
        ?ApiCapabilitiesService $capabilitiesService = null,
        ?SearchCursorCodec $cursorCodec = null,
        ?NzbUploadStagingService $nzbUploadStagingService = null,
        ?ApiInputCanonicalizer $inputCanonicalizer = null,
    ) {
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
        $this->releaseRowCache = $releaseRowCache ?? app(ApiReleaseRowCache::class);
        $this->queryParameters = $queryParameters ?? app(ApiQueryParameters::class);
        $this->usageService = $usageService ?? app(ApiUsageService::class);
        $this->userResolver = $userResolver ?? app(ApiUserResolver::class);
        $this->presenter = $presenter ?? app(ApiV2Presenter::class);
        $this->capabilitiesService = $capabilitiesService ?? app(ApiCapabilitiesService::class);
        $this->cursorCodec = $cursorCodec ?? app(SearchCursorCodec::class);
        $this->nzbUploadStagingService = $nzbUploadStagingService ?? app(NzbUploadStagingService::class);
        $this->inputCanonicalizer = $inputCanonicalizer ?? app(ApiInputCanonicalizer::class);
    }

    /**
     * Validate API token and return cached user, or a normalized JSON API error on failure.
     * Caches user lookup for 5 minutes to reduce DB hits.
     *
     * With $enforceRequestLimit, every authenticated request is checked against the
     * rolling daily quota and recorded in one atomic step, whether or not its
     * parameters later turn out to be invalid.
     */
    private function resolveUser(Request $request, bool $enforceRequestLimit = true): User|JsonResponse
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return apiJsonError(200, 'Missing parameter (api_token)');
        }

        $apiToken = $request->input('api_token');
        $resolved = $request->attributes->get('nntmux.api_user');
        $user = $resolved instanceof User ? $resolved : $this->userResolver->v2((string) $apiToken);

        if (! $user || ! $user->hasVerifiedEmail()) {
            return apiJsonError(100);
        }

        if ($user->is_disabled || $user->hasRole('Disabled')) {
            return apiJsonError(101);
        }

        $user->loadMissing('role');

        if ($enforceRequestLimit) {
            try {
                $used = $this->usageService->reserve($user, $request, (int) $user->role->apirequests);
            } catch (LockTimeoutException) {
                return apiJsonError(500, 'Too many concurrent requests, retry shortly');
            }

            if ($used === null) {
                return apiJsonError(500, 'Request limit reached');
            }

            // The display statistics are cached; report the count enforcement just saw.
            $userStats = clone $this->usageService->statistics($user->id);
            $userStats->api_count = $used;
            $this->resolvedUserStats[$user->id] = $userStats;
        }

        return $user;
    }

    /**
     * Build the standard user stats portion of an API response.
     * Uses the consolidated single-query + 60s cache from ApiController.
     *
     * @return array<string, mixed>
     */
    private function buildUserStatsResponse(User $user): array
    {
        $userStats = $this->userStatsFor($user);

        return [
            'apiCurrent' => (int) ($userStats->api_count ?? 0),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => (int) ($userStats->grab_count ?? 0),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $userStats->api_time ? Carbon::parse($userStats->api_time)->toRfc2822String() : '',
            'grabOldestTime' => $userStats->grab_time ? Carbon::parse($userStats->grab_time)->toRfc2822String() : '',
        ];
    }

    private function userStatsFor(User $user): object
    {
        return $this->resolvedUserStats[$user->id] ??= $this->usageService->statistics($user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonResponse(array $data, int $status = 200): JsonResponse
    {
        return $this->presenter->json($data, $status);
    }

    /**
     * Build the standard search-results JSON response.
     *
     * Replaces the legacy Fractal `['Results' => fractal(...)]` envelope with a
     * lower-case `results` array of {@see ReleaseData} payloads. Pagination
     * total and per-user API/grab quotas are kept inline alongside the array
     * because a JSON object cannot carry both top-level metadata fields and a
     * bare array body.
     *
     * @param  iterable<int, Release|\stdClass>  $rows
     */
    private function buildSearchResponse(iterable $rows, User $user): JsonResponse
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $pagination = null;
        if ($this->paginationContext['enabled']) {
            $total = (int) ($rows[0]->_totalrows ?? 0);
            $nextOffset = $this->paginationContext['offset'] + count($rows);
            $hasMore = (bool) ($rows[0]->_search_has_more ?? (count($rows) === $this->paginationContext['limit'] && $nextOffset < $total));
            $lastSort = $rows[0]->_search_last_sort ?? [];
            $cursorPosition = is_array($lastSort) && count($lastSort) === 2 ? array_values($lastSort) : [$nextOffset];
            $nextCursor = $hasMore ? $this->cursorCodec->encode(new SearchCursor(
                sortValues: $cursorPosition,
                total: $total,
                queryHash: $this->paginationContext['query_hash'],
                driver: Search::getCurrentDriver(),
                indexGeneration: (string) config('search.index_generation', '1'),
                expiresAt: time() + ((int) config('search.cursor_ttl_minutes', 15) * 60),
            )) : null;
            $pagination = ['next_cursor' => $nextCursor, 'has_more' => $hasMore];
        }

        return $this->presenter->search($rows, $user, $this->buildUserStatsResponse($user), $pagination);
    }

    private function resolvePaginationOffset(Request $request, int $limit): int|JsonResponse
    {
        if ($request->has('offset') && ! $this->isNonNegativeInteger($request->input('offset'))) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (offset must be a non-negative integer)'], 400);
        }
        $offset = $this->queryParameters->offset($request);
        if (! $request->has('cursor')) {
            $this->paginationContext = ['enabled' => false, 'offset' => $offset, 'limit' => $limit, 'query_hash' => '', 'cursor' => null];

            return $offset;
        }
        if ($offset > 0) {
            return $this->jsonResponse(['error' => 'cursor cannot be combined with a nonzero offset'], 400);
        }

        $queryHash = $this->cursorQueryHash($request);
        $token = trim((string) $request->input('cursor', ''));
        $cursor = null;
        if ($token !== '') {
            try {
                $cursor = $this->cursorCodec->decode($token);
            } catch (\InvalidArgumentException $e) {
                return $this->jsonResponse(['error' => $e->getMessage()], 400);
            }
            if ($cursor->queryHash !== $queryHash
                || $cursor->driver !== Search::getCurrentDriver()
                || $cursor->indexGeneration !== (string) config('search.index_generation', '1')) {
                return $this->jsonResponse(['error' => 'Search cursor does not match this query or index generation.'], 400);
            }
            $offset = count($cursor->sortValues) === 1 ? max(0, (int) $cursor->sortValues[0]) : 0;
        }

        $this->paginationContext = ['enabled' => true, 'offset' => $offset, 'limit' => $limit, 'query_hash' => $queryHash, 'cursor' => $cursor];

        return $offset;
    }

    /**
     * Bind cursors to the endpoint and the canonical effective input (URL and
     * body), so GET and QUERY with the same filters share cursors while any
     * other filter set, sort, or endpoint is rejected.
     */
    private function cursorQueryHash(Request $request): string
    {
        return hash('sha256', json_encode([
            'endpoint' => $request->path(),
            'params' => $this->inputCanonicalizer->canonicalize($request->except(['api_token', 'apikey', 'cursor', 'offset'])),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Page size: a positive integer, capped at the maximum advertised in capabilities.
     */
    private function parseLimit(Request $request): int|JsonResponse
    {
        if ($request->has('limit')
            && (! $this->isNonNegativeInteger($request->input('limit')) || (int) $request->input('limit') < 1)) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (limit must be a positive integer, at most '.ApiQueryParameters::MAX_LIMIT.' results are returned)'], 400);
        }

        return $this->queryParameters->limit($request);
    }

    private function isNonNegativeInteger(mixed $value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1);
    }

    private function parseMaxAge(Request $request): int|JsonResponse
    {
        if (! $request->has('maxage')) {
            return -1;
        }
        if ($request->isNotFilled('maxage')) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (maxage must not be empty)'], 400);
        }
        if (! is_numeric($request->input('maxage'))) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (maxage must be numeric)'], 400);
        }

        return (int) $request->input('maxage');
    }

    private function parseSort(Request $request): string|JsonResponse
    {
        if (! $request->has('sort')) {
            return 'posted_desc';
        }

        $sort = strtolower(trim((string) $request->input('sort')));
        if ($sort === '') {
            return $this->jsonResponse(['error' => 'Incorrect parameter (sort must not be empty)'], 400);
        }
        if (! preg_match('/^(cat|name|size|files|stats|posted)_(asc|desc)$/', $sort)) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (sort must be one of: cat_asc/desc, name_asc/desc, size_asc/desc, files_asc/desc, stats_asc/desc, posted_asc/desc)'], 400);
        }

        return $sort;
    }

    public function capabilities(Request $request): JsonResponse
    {
        $response = $this->jsonResponse($this->capabilitiesService->v2());
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setEtag(hash('sha256', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @throws \Throwable
     */
    public function movie(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        // Get request parameters efficiently
        $imdbId = (string) Str::replace('tt', '', (string) $request->input('imdbid', ''));
        $tmdbId = (int) $request->input('tmdbid', -1);
        $traktId = (int) $request->input('traktid', -1);
        $minSize = $this->queryParameters->minimumSize($request);
        $searchName = $request->input('id', '');
        if ($searchName === '' && ! imdb_id_is_valid($imdbId) && $tmdbId <= 0 && $traktId <= 0) {
            return $this->jsonResponse(['error' => 'Specify id (query), imdbid, tmdbid, or traktid'], 400);
        }
        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }
        $categoryID = $this->queryParameters->categories($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }
        $catExclusions = User::getCachedCategoryExclusionById($user->id);

        $relData = $this->releaseRowCache->remember('v2', 'movie', [
            'imdbid' => $imdbId,
            'tmdbid' => $tmdbId,
            'traktid' => $traktId,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'id' => $searchName,
            'sort' => $sort,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
        ], function () use (
            $imdbId, $tmdbId, $traktId, $offset, $limit, $searchName, $sort,
            $categoryID, $maxAge, $minSize, $catExclusions
        ) {
            return $this->releaseSearchService->moviesSearch(
                $imdbId,
                $tmdbId,
                $traktId,
                $offset,
                $limit,
                $searchName,
                $categoryID,
                $maxAge,
                $minSize,
                $catExclusions,
                $sort
            );
        });

        return $this->buildSearchResponse($relData, $user);
    }

    public function audio(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($request->has('id') && $request->isNotFilled('id')) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (id must not be empty)'], 400);
        }

        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }
        $categoryID = $this->queryParameters->categories($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $minSize = $this->queryParameters->minimumSize($request);
        $catExclusions = User::getCachedCategoryExclusionById($user->id);
        $groupName = $this->queryParameters->group($request);
        $searchName = (string) $request->input('id', '');

        if ($searchName === '') {
            if ($categoryID === [-1]) {
                $categoryID = [Category::MUSIC_ROOT];
            }
        }

        $relData = $this->releaseRowCache->remember('v2', 'audio', [
            'id' => $searchName,
            'group' => $groupName,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'sort' => $sort,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
        ], function () use ($searchName, $groupName, $offset, $limit, $sort, $maxAge, $catExclusions, $categoryID, $minSize) {
            if ($searchName === '') {
                return $this->releaseBrowseService->getBrowseRangeForApi(
                    1,
                    $categoryID,
                    $offset,
                    $limit,
                    $sort,
                    $maxAge,
                    $catExclusions,
                    $groupName,
                    $minSize
                );
            }

            return $this->releaseSearchService->apiMusicSearch(
                $searchName,
                $groupName,
                $offset,
                $limit,
                $maxAge,
                $catExclusions,
                $categoryID,
                $minSize,
                $sort
            );
        });

        return $this->buildSearchResponse($relData, $user);
    }

    public function books(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($request->has('id') && $request->isNotFilled('id')) {
            return $this->jsonResponse(['error' => 'Incorrect parameter (id must not be empty)'], 400);
        }

        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }
        $categoryID = $this->queryParameters->categories($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $minSize = $this->queryParameters->minimumSize($request);
        $catExclusions = User::getCachedCategoryExclusionById($user->id);
        $groupName = $this->queryParameters->group($request);
        $searchName = (string) $request->input('id', '');

        if ($searchName === '') {
            if ($categoryID === [-1]) {
                $categoryID = [Category::BOOKS_ROOT];
            }
        }

        $relData = $this->releaseRowCache->remember('v2', 'books', [
            'id' => $searchName,
            'group' => $groupName,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'sort' => $sort,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
        ], function () use ($searchName, $groupName, $offset, $limit, $sort, $maxAge, $catExclusions, $categoryID, $minSize) {
            if ($searchName === '') {
                return $this->releaseBrowseService->getBrowseRangeForApi(
                    1,
                    $categoryID,
                    $offset,
                    $limit,
                    $sort,
                    $maxAge,
                    $catExclusions,
                    $groupName,
                    $minSize
                );
            }

            return $this->releaseSearchService->apiBookSearch(
                $searchName,
                $groupName,
                $offset,
                $limit,
                $maxAge,
                $catExclusions,
                $categoryID,
                $minSize,
                $sort
            );
        });

        return $this->buildSearchResponse($relData, $user);
    }

    public function anime(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $q = (string) $request->input('id', '');
        $anidb = (int) $request->input('anidbid', -1);
        $anilist = (int) $request->input('anilistid', -1);
        if ($q === '' && $anidb <= 0 && $anilist <= 0) {
            return $this->jsonResponse(['error' => 'Specify id (query), anidbid, or anilistid'], 400);
        }

        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }
        $categoryID = $this->queryParameters->categories($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $catExclusions = User::getCachedCategoryExclusionById($user->id);
        $minSize = $this->queryParameters->minimumSize($request);

        $relData = $this->releaseRowCache->remember('v2', 'anime', [
            'id' => $q,
            'anidbid' => $anidb,
            'anilistid' => $anilist,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'sort' => $sort,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
        ], fn () => $this->releaseSearchService->animeSearch(
            $anidb,
            $offset,
            $limit,
            $q,
            $categoryID,
            $maxAge,
            $catExclusions,
            $anilist,
            $sort,
            $minSize
        ));

        return $this->buildSearchResponse($relData, $user);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function apiSearch(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $catExclusions = User::getCachedCategoryExclusionById($user->id);
        $minSize = $this->queryParameters->minimumSize($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }
        $groupName = $this->queryParameters->group($request);
        if (is_array($groupName)) {
            $groupName = $groupName[0] ?? -1;
        }
        $categoryID = $this->queryParameters->categories($request);
        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }

        $searchName = $request->input('id');
        $relData = $this->releaseRowCache->remember('v2', 'search', [
            'id' => $searchName,
            'group' => $groupName,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'sort' => $sort,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
        ], function () use ($request, $searchName, $groupName, $offset, $limit, $maxAge, $catExclusions, $categoryID, $minSize, $sort) {
            if ($request->has('id')) {
                return $this->releaseSearchService->apiSearch(
                    $searchName,
                    $groupName,
                    $offset,
                    $limit,
                    $maxAge,
                    $catExclusions,
                    $categoryID,
                    $minSize,
                    $sort,
                    $this->paginationContext['cursor'],
                );
            }

            return $this->releaseBrowseService->getBrowseRangeForApi(
                1,
                $categoryID,
                $offset,
                $limit,
                $sort,
                $maxAge,
                $catExclusions,
                $groupName,
                $minSize
            );
        });

        return $this->buildSearchResponse($relData, $user);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function tv(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $catExclusions = User::getCachedCategoryExclusionById($user->id);
        $minSize = $this->queryParameters->minimumSize($request);
        if (! $this->hasTvSearchParameters($request)) {
            return $this->jsonResponse(['error' => 'Specify id (query), vid, tvdbid, traktid, rid, tvmazeid, imdbid, or tmdbid'], 400);
        }
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $siteIdArr = [
            'id' => $request->input('vid') ?? null,
            'tvdb' => $request->input('tvdbid') ?? null,
            'trakt' => $request->input('traktid') ?? null,
            'tvrage' => $request->input('rid') ?? null,
            'tvmaze' => $request->input('tvmazeid') ?? null,
            'imdb' => $request->input('imdbid') ?? null,
            'tmdb' => $request->input('tmdbid') ?? null,
        ];

        // Process season only queries or Season and Episode/Airdate queries

        $series = $request->input('season') ?? '';
        $episode = $request->input('ep') ?? '';

        if (preg_match('#^(19|20)\d{2}$#', $series, $year) && str_contains($episode, '/')) {
            $airDate = str_replace('/', '-', $year[0].'-'.$episode);
        }

        $limit = $this->parseLimit($request);
        if (! is_int($limit)) {
            return $limit;
        }
        $offset = $this->resolvePaginationOffset($request, $limit);
        if (! is_int($offset)) {
            return $offset;
        }
        $categoryID = $this->queryParameters->categories($request);
        $airDate = $airDate ?? '';
        $searchName = $request->input('id') ?? '';

        $relData = $this->releaseRowCache->remember('v2', 'tv', [
            'site_ids' => $siteIdArr,
            'season' => $series,
            'episode' => $episode,
            'air_date' => $airDate,
            'offset' => $offset,
            'cursor' => $request->input('cursor'),
            'limit' => $limit,
            'id' => $searchName,
            'category' => $categoryID,
            'max_age' => $maxAge,
            'min_size' => $minSize,
            'excluded' => $catExclusions,
            'sort' => $sort,
        ], fn () => $this->releaseSearchService->apiTvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airDate,
            $offset,
            $limit,
            $searchName,
            $categoryID,
            $maxAge,
            $minSize,
            $catExclusions,
            $sort
        ));

        return $this->buildSearchResponse($relData, $user);
    }

    /**
     * Stream a single NZB. Errors from the download path (missing file, exhausted
     * download allowance) are rendered as JSON like every other v2 error.
     */
    public function getNzb(Request $request): JsonResponse|Response|StreamedResponse|Builder
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $guid = $this->scalarInput($request, 'id');
        if ($guid === '') {
            return apiJsonError(200, 'Missing parameter (id)');
        }

        if (! Release::checkGuidForApi(str_ireplace('.nzb', '', $guid))) {
            return $this->jsonResponse(['error' => 'No such item (the guid you provided has no release in our database)'], 404);
        }

        $request->attributes->set(GetNzbController::REQUEST_USER_ATTRIBUTE, $user);
        $request->attributes->set(GetNzbController::JSON_ERRORS_ATTRIBUTE, true);

        return app(GetNzbController::class)->getNzb($request);
    }

    public function nzbAdd(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request, false);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $user->can_post) {
            return $this->jsonResponse(['error' => 'Insufficient privileges/not authorized'], 403);
        }

        $nzb = $request->file('nzb');
        if (! $nzb instanceof UploadedFile) {
            return $this->jsonResponse(['error' => 'Missing parameter (nzb file is required)'], 400);
        }

        $nfo = $request->file('nfo');
        if ($nfo !== null && ! $nfo instanceof UploadedFile) {
            return $this->jsonResponse(['error' => 'Invalid nfo upload'], 400);
        }

        try {
            $staged = $this->nzbUploadStagingService->stage($nzb, $nfo);
        } catch (NzbUploadException $exception) {
            return $this->jsonResponse(['error' => $exception->getMessage()], $exception->status);
        }

        return $this->jsonResponse([
            'success' => true,
            'status' => 'staged',
            'name' => $staged['name'],
            'category' => $request->input('cat'),
            'files' => $staged['files'],
        ], 201);
    }

    public function details(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($request->missing('id')) {
            return $this->jsonResponse(['error' => 'Missing parameter (guid is required for single release details)'], 400);
        }

        $guid = $request->input('id');
        $relData = $this->releaseRowCache->remember('v2', 'details', [
            'guid' => $guid,
        ], fn () => Release::getByGuidForApi($guid, false));

        if ($relData === null) {
            return $this->jsonResponse(['error' => 'No such item'], 404);
        }

        return $this->presenter->details($relData, $user);
    }

    private function hasTvSearchParameters(Request $request): bool
    {
        return $request->filled('id')
            || $request->filled('vid')
            || $request->filled('tvdbid')
            || $request->filled('traktid')
            || $request->filled('rid')
            || $request->filled('tvmazeid')
            || $request->filled('imdbid')
            || $request->filled('tmdbid');
    }
}
