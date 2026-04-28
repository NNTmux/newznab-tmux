<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Api\CategoryData;
use App\Data\Api\DetailsData;
use App\Data\Api\ReleaseData;
use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Release;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\RegistrationStatusService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ApiV2Controller extends BasePageController
{
    private ApiController $api;

    private ReleaseSearchService $releaseSearchService;

    private ReleaseBrowseService $releaseBrowseService;

    public function __construct(
        ApiController $api,
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService
    ) {
        $this->api = $api;
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
    }

    /**
     * Validate API token and return cached user, or a normalized JSON API error on failure.
     * Caches user lookup for 5 minutes to reduce DB hits.
     */
    private function resolveUser(Request $request): User|JsonResponse
    {
        if ($request->missing('api_token') || $request->isNotFilled('api_token')) {
            return apiJsonError(200, 'Missing parameter (api_token)');
        }

        $apiToken = $request->input('api_token');
        $userCacheKey = 'api_user:'.md5((string) $apiToken);

        $user = Cache::remember($userCacheKey, 300, function () use ($apiToken) {
            return User::query()
                ->whereApiToken((string) $apiToken)
                ->first();
        });

        if (! $user || ! $user->hasVerifiedEmail()) {
            return apiJsonError(100);
        }

        if ($user->is_disabled || $user->hasRole('Disabled')) {
            return apiJsonError(101);
        }

        $user->loadMissing('role');

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
        $userStats = $this->api->getCachedUserStats($user->id);

        return [
            'apiCurrent' => (int) ($userStats->api_count ?? 0),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => (int) ($userStats->grab_count ?? 0),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $userStats->api_time ? Carbon::parse($userStats->api_time)->toRfc2822String() : '',
            'grabOldestTime' => $userStats->grab_time ? Carbon::parse($userStats->grab_time)->toRfc2822String() : '',
        ];
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
        $rowsArray = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $total = (int) ($rowsArray[0]->_totalrows ?? 0);

        $results = array_map(
            static fn ($row): array => ReleaseData::fromRelease($row, $user)->toArray(),
            $rowsArray,
        );

        return response()->json(array_merge(
            ['Total' => $total],
            $this->buildUserStatsResponse($user),
            ['results' => $results],
        ));
    }

    private function parseMaxAge(Request $request): int|JsonResponse
    {
        if (! $request->has('maxage')) {
            return -1;
        }
        if ($request->isNotFilled('maxage')) {
            return response()->json(['error' => 'Incorrect parameter (maxage must not be empty)'], 400);
        }
        if (! is_numeric($request->input('maxage'))) {
            return response()->json(['error' => 'Incorrect parameter (maxage must be numeric)'], 400);
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
            return response()->json(['error' => 'Incorrect parameter (sort must not be empty)'], 400);
        }
        if (! preg_match('/^(cat|name|size|files|stats|posted)_(asc|desc)$/', $sort)) {
            return response()->json(['error' => 'Incorrect parameter (sort must be one of: cat_asc/desc, name_asc/desc, size_asc/desc, files_asc/desc, stats_asc/desc, posted_asc/desc)'], 400);
        }

        return $sort;
    }

    public function capabilities(): JsonResponse
    {
        // Cache the full capabilities response for 10 minutes
        $capabilities = Cache::remember('api_v2_capabilities', 600, function () {
            $category = Category::getForApi();

            return [
                'server' => [
                    'title' => config('app.name'),
                    'strapline' => Settings::settingValue('strapline'),
                    'email' => config('mail.from.address'),
                    'url' => url('/'),
                ],
                'limits' => [
                    'max' => 100,
                    'default' => 100,
                ],
                'searching' => [
                    'search' => ['available' => 'yes', 'supportedParams' => 'id,group,minsize,maxsize,maxage,cat,limit,offset,sort'],
                    'tv-search' => ['available' => 'yes', 'supportedParams' => 'id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep,cat,minsize,maxsize,maxage,limit,offset,sort'],
                    'movie-search' => ['available' => 'yes', 'supportedParams' => 'id,imdbid,tmdbid,traktid,genre,cat,minsize,maxsize,maxage,limit,offset,sort'],
                    'audio-search' => ['available' => 'yes', 'supportedParams' => 'id,cat,minsize,maxsize,maxage,group,limit,offset,sort'],
                    'book-search' => ['available' => 'yes', 'supportedParams' => 'id,cat,minsize,maxsize,maxage,group,limit,offset,sort'],
                    'anime-search' => ['available' => 'yes', 'supportedParams' => 'id,anidbid,anilistid,cat,minsize,maxsize,maxage,limit,offset,sort'],
                ],
                'categories' => $category->map(static fn ($c) => CategoryData::fromCategory($c))->values(),
                'groups' => Schema::hasTable('usenet_groups')
                    ? UsenetGroup::query()
                        ->where('active', 1)
                        ->orderBy('name')
                        ->get(['name', 'description', 'last_updated'])
                        ->map(static fn (UsenetGroup $group): array => [
                            'name' => $group->name,
                            'description' => (string) ($group->description ?? ''),
                            'lastupdate' => $group->last_updated ? Carbon::parse($group->last_updated)->toRfc2822String() : '',
                        ])
                        ->values()
                    : collect(),
                'genres' => Schema::hasTable('genres')
                    ? Genre::query()
                        ->enabled()
                        ->orderBy('title')
                        ->get(['id', 'title', 'type'])
                        ->map(static fn (Genre $genre): array => [
                            'id' => $genre->id,
                            'name' => $genre->title,
                            'categoryid' => (int) ($genre->type ?? 0),
                        ])
                        ->values()
                    : collect(),
            ];
        });

        $registrationStatus = app(RegistrationStatusService::class)->resolve();
        $capabilities['registration'] = [
            'available' => $registrationStatus['available'] ? 'yes' : 'no',
            'open' => $registrationStatus['is_open'] ? 'yes' : 'no',
        ];

        return response()->json($capabilities);
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

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

        // Get request parameters efficiently
        $imdbId = (string) Str::replace('tt', '', (string) $request->input('imdbid', ''));
        $tmdbId = (int) $request->input('tmdbid', -1);
        $traktId = (int) $request->input('traktid', -1);
        $minSize = max(0, (int) $request->input('minsize', 0));
        $searchName = $request->input('id', '');
        if ($searchName === '' && ! imdb_id_is_valid($imdbId) && $tmdbId <= 0 && $traktId <= 0) {
            return response()->json(['error' => 'Specify id (query), imdbid, tmdbid, or traktid'], 400);
        }
        $offset = $this->api->offset($request);
        $limit = $this->api->limit($request);
        $categoryID = $this->api->categoryID($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }
        $catExclusions = User::getCategoryExclusionById($user->id);

        // Create cache key for movie search results
        $searchCacheKey = 'api_movie_search:'.md5(serialize([
            $imdbId, $tmdbId, $traktId, $offset, $limit, $searchName, $sort,
            $categoryID, $maxAge, $minSize, $catExclusions,
        ]));

        // Cache search results for 10 minutes
        $relData = Cache::remember($searchCacheKey, 600, function () use (
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

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

        $q = (string) $request->input('id', '');
        if ($q === '') {
            return response()->json(['error' => 'Missing id (search query)'], 400);
        }

        $offset = $this->api->offset($request);
        $limit = $this->api->limit($request);
        $categoryID = $this->api->categoryID($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $minSize = max(0, (int) $request->input('minsize', 0));
        $catExclusions = User::getCategoryExclusionById($user->id);
        $groupName = $this->api->group($request);

        $relData = $this->releaseSearchService->apiMusicSearch(
            $q,
            $groupName,
            $offset,
            $limit,
            $maxAge,
            $catExclusions,
            $categoryID,
            $minSize,
            $sort
        );

        return $this->buildSearchResponse($relData, $user);
    }

    public function books(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

        $q = (string) $request->input('id', '');
        if ($q === '') {
            return response()->json(['error' => 'Missing id (search query)'], 400);
        }

        $offset = $this->api->offset($request);
        $limit = $this->api->limit($request);
        $categoryID = $this->api->categoryID($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $minSize = max(0, (int) $request->input('minsize', 0));
        $catExclusions = User::getCategoryExclusionById($user->id);
        $groupName = $this->api->group($request);

        $relData = $this->releaseSearchService->apiBookSearch(
            $q,
            $groupName,
            $offset,
            $limit,
            $maxAge,
            $catExclusions,
            $categoryID,
            $minSize,
            $sort
        );

        return $this->buildSearchResponse($relData, $user);
    }

    public function anime(Request $request): JsonResponse|Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

        $q = (string) $request->input('id', '');
        $anidb = (int) $request->input('anidbid', -1);
        $anilist = (int) $request->input('anilistid', -1);
        if ($q === '' && $anidb <= 0 && $anilist <= 0) {
            return response()->json(['error' => 'Specify id (query), anidbid, or anilistid'], 400);
        }

        $offset = $this->api->offset($request);
        $limit = $this->api->limit($request);
        $categoryID = $this->api->categoryID($request);
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }

        $catExclusions = User::getCategoryExclusionById($user->id);

        $relData = $this->releaseSearchService->animeSearch(
            $anidb,
            $offset,
            $limit,
            $q,
            $categoryID,
            $maxAge,
            $catExclusions,
            $anilist,
            $sort
        );

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

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

        $offset = $this->api->offset($request);
        $catExclusions = User::getCategoryExclusionById($user->id);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }
        $groupName = $this->api->group($request);
        if (is_array($groupName)) {
            $groupName = $groupName[0] ?? -1;
        }
        $categoryID = $this->api->categoryID($request);
        $limit = $this->api->limit($request);

        if ($request->has('id')) {
            $relData = $this->releaseSearchService->apiSearch(
                $request->input('id'),
                $groupName,
                $offset,
                $limit,
                $maxAge,
                $catExclusions,
                $categoryID,
                $minSize,
                $sort
            );
        } else {
            $relData = $this->releaseBrowseService->getBrowseRangeForApi(
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

        $catExclusions = User::getCategoryExclusionById($user->id);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $this->api->verifyEmptyParameter($request, 'id');
        $this->api->verifyEmptyParameter($request, 'vid');
        $this->api->verifyEmptyParameter($request, 'tvdbid');
        $this->api->verifyEmptyParameter($request, 'traktid');
        $this->api->verifyEmptyParameter($request, 'rid');
        $this->api->verifyEmptyParameter($request, 'tvmazeid');
        $this->api->verifyEmptyParameter($request, 'imdbid');
        $this->api->verifyEmptyParameter($request, 'tmdbid');
        $this->api->verifyEmptyParameter($request, 'season');
        $this->api->verifyEmptyParameter($request, 'ep');
        if (! $this->hasTvSearchParameters($request)) {
            return response()->json(['error' => 'Specify id (query), vid, tvdbid, traktid, rid, tvmazeid, imdbid, or tmdbid'], 400);
        }
        $maxAge = $this->parseMaxAge($request);
        if (! is_int($maxAge)) {
            return $maxAge;
        }
        $sort = $this->parseSort($request);
        if (! is_string($sort)) {
            return $sort;
        }
        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));

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

        $relData = $this->releaseSearchService->apiTvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airDate ?? '',
            $this->api->offset($request),
            $this->api->limit($request),
            $request->input('id') ?? '',
            $this->api->categoryID($request),
            $maxAge,
            $minSize,
            $catExclusions,
            $sort
        );

        return $this->buildSearchResponse($relData, $user);
    }

    public function getNzb(Request $request): Application|ResponseFactory|JsonResponse|Redirector|RedirectResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        event(new UserAccessedApi($user, $request->ip()));
        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        $relData = Release::checkGuidForApi($request->input('id'));
        if ($relData) {
            return redirect('/getnzb?r='.$request->input('api_token').'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : ''));
        }

        return response()->json(['data' => 'No such item (the guid you provided has no release in our database)'], 404);
    }

    public function details(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($request->missing('id')) {
            return response()->json(['error' => 'Missing parameter (guid is required for single release details)'], 400);
        }

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        event(new UserAccessedApi($user, $request->ip()));
        $relData = Release::getByGuidForApi($request->input('id'));

        if ($relData === null) {
            return response()->json(['error' => 'No such item'], 404);
        }

        return response()->json(DetailsData::fromRelease($relData, $user)->toArray());
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
