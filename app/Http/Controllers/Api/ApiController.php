<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\NzbUploadException;
use App\Http\Controllers\BasePageController;
use App\Http\Controllers\GetNzbController;
use App\Models\Category;
use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\User;
use App\Services\Api\ApiCapabilitiesService;
use App\Services\Api\ApiQueryParameters;
use App\Services\Api\ApiReleaseRowCache;
use App\Services\Api\ApiUsageService;
use App\Services\Api\ApiUserResolver;
use App\Services\Api\V1\ApiV1Presenter;
use App\Services\Nzb\NzbUploadStagingService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Support\FilenameSanitizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends BasePageController
{
    /**
     * Functions reachable over HTTP QUERY, which RFC 10008 defines as safe.
     * `g` (download accounting, cart deletion with del=1) and `nzbAdd` are excluded.
     */
    private const QUERY_SAFE_FUNCTIONS = ['s', 'tv', 'm', 'music', 'book', 'anime', 'd', 'c', 'n'];

    private string $type;

    protected ReleaseSearchService $releaseSearchService;

    protected ReleaseBrowseService $releaseBrowseService;

    protected ApiReleaseRowCache $releaseRowCache;

    private ?ApiQueryParameters $queryParameters = null;

    private ApiUsageService $usageService;

    private ApiUserResolver $userResolver;

    private ApiCapabilitiesService $capabilitiesService;

    private ApiV1Presenter $presenter;

    private NzbUploadStagingService $nzbUploadStagingService;

    public function __construct(
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService,
        ?ApiReleaseRowCache $releaseRowCache = null,
        ?ApiQueryParameters $queryParameters = null,
        ?ApiUsageService $usageService = null,
        ?ApiUserResolver $userResolver = null,
        ?ApiCapabilitiesService $capabilitiesService = null,
        ?ApiV1Presenter $presenter = null,
        ?NzbUploadStagingService $nzbUploadStagingService = null,
    ) {
        parent::__construct();
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
        $this->releaseRowCache = $releaseRowCache ?? app(ApiReleaseRowCache::class);
        $this->queryParameters = $queryParameters ?? app(ApiQueryParameters::class);
        $this->usageService = $usageService ?? app(ApiUsageService::class);
        $this->userResolver = $userResolver ?? app(ApiUserResolver::class);
        $this->capabilitiesService = $capabilitiesService ?? app(ApiCapabilitiesService::class);
        $this->presenter = $presenter ?? app(ApiV1Presenter::class);
        $this->nzbUploadStagingService = $nzbUploadStagingService ?? app(NzbUploadStagingService::class);
    }

    /**
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector|Response|StreamedResponse|void
     *
     * @throws \Throwable
     */
    public function api(Request $request)
    {
        // API functions.
        $function = 's';
        if ($request->has('t')) {
            switch ($request->input('t')) {
                case 'd':
                case 'details':
                    $function = 'd';
                    break;
                case 'g':
                case 'get':
                    $function = 'g';
                    break;
                case 's':
                case 'search':
                    break;
                case 'c':
                case 'caps':
                    $function = 'c';
                    break;
                case 'tv':
                case 'tvsearch':
                    $function = 'tv';
                    break;
                case 'm':
                case 'movie':
                    $function = 'm';
                    break;
                case 'music':
                case 'audio':
                    $function = 'music';
                    break;
                case 'b':
                case 'book':
                    $function = 'book';
                    break;
                case 'anime':
                    $function = 'anime';
                    break;
                case 'gn':
                case 'n':
                case 'nfo':
                case 'info':
                    $function = 'n';
                    break;
                case 'nzbadd':
                    $function = 'nzbAdd';
                    break;
                default:
                    return showApiError(202, 'No such function ('.$request->input('t').')');
            }
        } else {
            return showApiError(200, 'Missing parameter (t)');
        }

        if ($request->isMethod('QUERY') && ! in_array($function, self::QUERY_SAFE_FUNCTIONS, true)) {
            return showApiError(203, 'Function not available via QUERY');
        }

        $uid = $apiKey = $oldestGrabTime = $thisOldestTime = '';
        $res = $catExclusions = [];
        $maxRequests = $thisRequests = $maxDownloads = $grabs = 0;

        // Page is accessible only by the apikey

        if ($function !== 'c' && $function !== 'r') { // @phpstan-ignore notIdentical.alwaysTrue
            if ($request->missing('apikey') || ($request->has('apikey') && empty($request->input('apikey')))) {
                return showApiError(200, 'Missing parameter (apikey)');
            }

            $apiKey = $request->input('apikey');

            // Cache user lookup for 5 minutes to avoid repeated DB hits (same pattern as API v2)
            $resolved = $request->attributes->get('nntmux.api_user');
            $res = $resolved instanceof User ? $resolved : $this->userResolver->v1((string) $apiKey);

            if ($res === null) {
                return showApiError(100, 'Incorrect user credentials (wrong API key)');
            }

            if ($res->is_disabled || $res->hasRole('Disabled')) {
                return showApiError(101);
            }

            $uid = $res->id;
            // Use user ID directly instead of re-looking up by token
            $catExclusions = User::getCachedCategoryExclusionById($uid);
            $maxRequests = $res->role->apirequests;
            $maxDownloads = $res->role->downloadrequests;

            // Consolidated user stats: single query with 60s cache instead of 4 separate queries
            $userStats = $this->getCachedUserStats($uid);
            $thisOldestTime = $userStats->api_time ? Carbon::parse($userStats->api_time)->toRfc2822String() : '';
            $oldestGrabTime = $userStats->grab_time ? Carbon::parse($userStats->grab_time)->toRfc2822String() : '';
            $thisRequests = (int) ($userStats->api_count ?? 0);
            $grabs = (int) ($userStats->grab_count ?? 0);
        }

        // Quota enforcement happens before endpoint-specific request recording.
        if ($uid !== '') {
            if ($thisRequests >= $maxRequests) {
                return showApiError(500, 'Request limit reached ('.$thisRequests.'/'.$maxRequests.')');
            }
        }

        // Set Query Parameters based on Request objects
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? (int) $request->input('minsize') : 0;
        $offset = $this->offset($request);

        // Set API Parameters based on Request objects
        $params['extended'] = $request->has('extended') && (int) $request->input('extended') === 1 ? '1' : '0';
        $params['del'] = $request->has('del') && (int) $request->input('del') === 1 ? '1' : '0';
        $params['uid'] = $uid;
        $params['token'] = $apiKey;
        $params['apilimit'] = $maxRequests;
        $params['requests'] = $thisRequests;
        $params['downloadlimit'] = $maxDownloads;
        $params['grabs'] = $grabs;
        $params['oldestapi'] = $thisOldestTime;
        $params['oldestgrab'] = $oldestGrabTime;

        switch ($function) {
            // Search releases.
            case 's':
                $emptyParameterError = $this->verifyEmptyParameter($request, 'q');
                if ($emptyParameterError !== null) {
                    return $emptyParameterError;
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $groupName = $this->group($request);
                $this->usageService->record($res, $request);
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                $searchName = $request->input('q');
                $relData = $this->releaseRowCache->remember('v1', 'search', [
                    'q' => $searchName,
                    'group' => $groupName,
                    'offset' => $offset,
                    'limit' => $limit,
                    'sort' => $sort,
                    'category' => $categoryID,
                    'max_age' => $maxAge,
                    'min_size' => $minSize,
                    'excluded' => $catExclusions,
                ], function () use ($request, $searchName, $groupName, $offset, $limit, $maxAge, $catExclusions, $categoryID, $minSize, $sort) {
                    if ($request->has('q')) {
                        return $this->releaseSearchService->apiSearch(
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

                return $this->output($relData, $params, $outputXML, $offset, 'api');
                // Search tv releases.
            case 'tv':
                foreach (['q', 'vid', 'tvdbid', 'traktid', 'rid', 'tvmazeid', 'imdbid', 'tmdbid', 'season', 'ep'] as $parameter) {
                    $emptyParameterError = $this->verifyEmptyParameter($request, $parameter);
                    if ($emptyParameterError !== null) {
                        return $emptyParameterError;
                    }
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $this->usageService->record($res, $request);
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $this->hasTvSearchParameters($request)) {
                    if ($categoryID === [-1]) {
                        $categoryID = Category::TV_GROUP;
                    }

                    $relData = $this->releaseRowCache->remember('v1', 'tv', [
                        'has_search' => false,
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort' => $sort,
                        'category' => $categoryID,
                        'max_age' => $maxAge,
                        'min_size' => $minSize,
                        'excluded' => $catExclusions,
                    ], fn () => $this->releaseBrowseService->getBrowseRangeForApi(
                        1,
                        $categoryID,
                        $offset,
                        $limit,
                        $sort,
                        $maxAge,
                        $catExclusions,
                        -1,
                        $minSize
                    ));

                    return $this->output($relData, $params, $outputXML, $offset, 'api');
                }

                $siteIdArr = [
                    'id' => $request->input('vid') ?? '0',
                    'tvdb' => $request->input('tvdbid') ?? '0',
                    'trakt' => $request->input('traktid') ?? '0',
                    'tvrage' => $request->input('rid') ?? '0',
                    'tvmaze' => $request->input('tvmazeid') ?? '0',
                    /** @phpstan-ignore argument.templateType */
                    'imdb' => Str::replace('tt', '', $request->input('imdbid')) ?? '0',
                    'tmdb' => $request->input('tmdbid') ?? '0',
                ];

                // Process season only queries or Season and Episode/Airdate queries

                $series = $request->input('season') ?? '';
                $episode = $request->input('ep') ?? '';

                if (preg_match('#^(19|20)\d{2}$#', $series, $year) && str_contains($episode, '/')) {
                    $airDate = str_replace('/', '-', $year[0].'-'.$episode);
                }

                $airDate = $airDate ?? '';
                $searchName = $request->input('q') ?? '';
                $relData = $this->releaseRowCache->remember('v1', 'tv', [
                    'has_search' => true,
                    'site_ids' => $siteIdArr,
                    'season' => $series,
                    'episode' => $episode,
                    'air_date' => $airDate,
                    'offset' => $offset,
                    'limit' => $limit,
                    'q' => $searchName,
                    'category' => $categoryID,
                    'max_age' => $maxAge,
                    'min_size' => $minSize,
                    'excluded' => $catExclusions,
                    'sort' => $sort,
                ], fn () => $this->releaseSearchService->tvSearch(
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

                return $this->output($relData, $params, $outputXML, $offset, 'api');

                // Search movie releases.
            case 'm':
                foreach (['q', 'imdbid', 'tmdbid', 'traktid'] as $parameter) {
                    $emptyParameterError = $this->verifyEmptyParameter($request, $parameter);
                    if ($emptyParameterError !== null) {
                        return $emptyParameterError;
                    }
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $this->usageService->record($res, $request);
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $this->hasMovieSearchParameters($request)) {
                    if ($categoryID === [-1]) {
                        $categoryID = Category::MOVIES_GROUP;
                    }

                    $relData = $this->releaseRowCache->remember('v1', 'movie', [
                        'has_search' => false,
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort' => $sort,
                        'category' => $categoryID,
                        'max_age' => $maxAge,
                        'min_size' => $minSize,
                        'excluded' => $catExclusions,
                    ], fn () => $this->releaseBrowseService->getBrowseRangeForApi(
                        1,
                        $categoryID,
                        $offset,
                        $limit,
                        $sort,
                        $maxAge,
                        $catExclusions,
                        -1,
                        $minSize
                    ));

                    return $this->output($relData, $params, $outputXML, $offset, 'api');
                }

                $imdbId = $request->has('imdbid') && $request->filled('imdbid')
                    ? (string) Str::replace('tt', '', (string) $request->input('imdbid'))
                    : '';
                $tmdbId = $request->has('tmdbid') && $request->filled('tmdbid') ? (int) $request->input('tmdbid') : -1;
                $traktId = $request->has('traktid') && $request->filled('traktid') ? (int) $request->input('traktid') : -1;

                $searchName = $request->input('q') ?? '';
                $relData = $this->releaseRowCache->remember('v1', 'movie', [
                    'has_search' => true,
                    'imdbid' => $imdbId,
                    'tmdbid' => $tmdbId,
                    'traktid' => $traktId,
                    'offset' => $offset,
                    'limit' => $limit,
                    'q' => $searchName,
                    'sort' => $sort,
                    'category' => $categoryID,
                    'max_age' => $maxAge,
                    'min_size' => $minSize,
                    'excluded' => $catExclusions,
                ], fn () => $this->releaseSearchService->moviesSearch(
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
                ));

                $this->addCoverURL(
                    $relData,
                    function ($release) {
                        return getCoverURL(['type' => 'movies', 'id' => $release->imdbid]);
                    }
                );

                return $this->output($relData, $params, $outputXML, $offset, 'api');

            case 'music':
                if ($request->has('q') && ! $request->filled('q')) {
                    return showApiError(201, 'Incorrect parameter (q must not be empty)');
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $groupName = $this->group($request);
                $this->usageService->record($res, $request);
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                $searchName = (string) $request->input('q', '');
                if ($searchName === '') {
                    if ($categoryID === [-1]) {
                        $categoryID = [Category::MUSIC_ROOT];
                    }
                }

                $relData = $this->releaseRowCache->remember('v1', 'music', [
                    'q' => $searchName,
                    'group' => $groupName,
                    'offset' => $offset,
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

                return $this->output($relData, $params, $outputXML, $offset, 'api');

            case 'book':
                if ($request->has('q') && ! $request->filled('q')) {
                    return showApiError(201, 'Incorrect parameter (q must not be empty)');
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $groupName = $this->group($request);
                $this->usageService->record($res, $request);
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                $searchName = (string) $request->input('q', '');
                if ($searchName === '') {
                    if ($categoryID === [-1]) {
                        $categoryID = [Category::BOOKS_ROOT];
                    }
                }

                $relData = $this->releaseRowCache->remember('v1', 'book', [
                    'q' => $searchName,
                    'group' => $groupName,
                    'offset' => $offset,
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

                return $this->output($relData, $params, $outputXML, $offset, 'api');

            case 'anime':
                $q = (string) ($request->input('q') ?? '');
                $anidb = $request->has('anidbid') && $request->filled('anidbid') ? (int) $request->input('anidbid') : -1;
                $anilist = $request->has('anilistid') && $request->filled('anilistid') ? (int) $request->input('anilistid') : -1;
                if ($q === '' && $anidb <= 0 && $anilist <= 0) {
                    return showApiError(200, 'Missing parameter (specify q, anidbid, or anilistid)');
                }
                $maxAge = $this->maxAge($request);
                if (! is_int($maxAge)) {
                    return $maxAge;
                }
                $sort = $this->sort($request);
                if (! is_string($sort)) {
                    return $sort;
                }
                $this->usageService->record($res, $request);
                $limit = $this->limit($request);
                $categoryID = $this->categoryID($request);
                $relData = $this->releaseRowCache->remember('v1', 'anime', [
                    'q' => $q,
                    'anidbid' => $anidb,
                    'anilistid' => $anilist,
                    'offset' => $offset,
                    'limit' => $limit,
                    'sort' => $sort,
                    'category' => $categoryID,
                    'max_age' => $maxAge,
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
                    $sort
                ));

                return $this->output($relData, $params, $outputXML, $offset, 'api');

                // Get NZB.
            case 'g':
                $emptyParameterError = $this->verifyEmptyParameter($request, 'g');
                if ($emptyParameterError !== null) {
                    return $emptyParameterError;
                }
                $this->usageService->record($res, $request);
                $relData = Release::checkGuidForApi($request->input('id'));
                if ($relData) {
                    $request->attributes->set(GetNzbController::REQUEST_USER_ATTRIBUTE, $res);

                    return app(GetNzbController::class)->getNzb($request);
                }

                return showApiError(300, 'No such item (the guid you provided has no release in our database)');

                // Get individual NZB details.
            case 'd':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (guid is required for single release details)');
                }

                $this->usageService->record($res, $request);
                $guid = $request->input('id');
                $data = $this->releaseRowCache->remember('v1', 'details', [
                    'guid' => $guid,
                ], fn () => Release::getByGuidForApi($guid));

                return $this->output($data, $params, $outputXML, $offset, 'api');

                // Get an NFO file for an individual release.
            case 'n':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
                }

                $this->usageService->record($res, $request);
                $rel = Release::query()->where('guid', $request->input('id'))->first(['id', 'searchname']);

                if ($rel) {
                    $data = ReleaseNfo::getReleaseNfo($rel->id);
                    if (! empty($data)) {
                        if ($request->has('o') && $request->input('o') === 'file') {
                            $filename = FilenameSanitizer::sanitize($rel->searchname, "nfo-{$rel->id}");
                            $asciiFallback = FilenameSanitizer::asciiFallback($filename, "nfo-{$rel->id}");

                            $response = response()->stream(function () use ($data) {
                                echo $data['nfo'];
                            }, 200, ['Content-Type' => 'application/octet-stream']);

                            $response->headers->set(
                                'Content-Disposition',
                                HeaderUtils::makeDisposition(
                                    'attachment',
                                    $filename.'.nfo',
                                    $asciiFallback.'.nfo'
                                )
                            );

                            return $response;
                        }

                        echo nl2br(cp437toUTF($data['nfo']));
                    } else {
                        return showApiError(300, 'Release does not have an NFO file associated.');
                    }
                } else {
                    return showApiError(300, 'Release does not exist.');
                }
                break;
                //
                // Paired NZB / NFO add request. The legacy single `file` field remains supported.
                //
            case 'nzbAdd':
                if (! User::canPost($uid)) {
                    return showApiError(102, 'Insufficient privileges/not authorized');
                }

                $usesLegacyField = $request->has('file') || $request->hasFile('file');
                $usesModernFields = $request->hasAny(['nzb', 'nfo'])
                    || $request->hasFile('nzb')
                    || $request->hasFile('nfo');

                if ($usesLegacyField && $usesModernFields) {
                    return showApiError(201, 'Use either file or nzb/nfo upload fields, not both');
                }
                if (! $usesLegacyField && ! $usesModernFields) {
                    return showApiError(200, 'Missing parameter (nzb file is required)');
                }

                $this->usageService->record($res, $request);

                try {
                    if ($usesModernFields) {
                        $nzb = $request->file('nzb');
                        if (! $nzb instanceof UploadedFile) {
                            return showApiError(200, 'Missing parameter (nzb file is required)');
                        }

                        $nfo = $request->file('nfo');
                        if ($nfo !== null && ! $nfo instanceof UploadedFile) {
                            return showApiError(600, 'Invalid nfo upload');
                        }

                        $staged = $this->nzbUploadStagingService->stage($nzb, $nfo);
                        $name = $staged['name'];
                    } else {
                        $file = $request->file('file');
                        if (! $file instanceof UploadedFile) {
                            return showApiError(600, 'Failed to load upload (no file)');
                        }

                        $name = $this->nzbUploadStagingService->stageLegacyFile($file)['name'];
                    }
                } catch (NzbUploadException $exception) {
                    return showApiError(600, $exception->getMessage());
                }

                $successXml = sprintf(
                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<success id=\"0\" guid=\"\" categoryid=\"%s\" name=\"%s\" />\n",
                    (string) $request->input('cat', ''),
                    htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                );

                return response($successXml, 200)->header('Content-type', 'text/xml');

                // Capabilities request.
            case 'c':
                return $this->output([], $params, $outputXML, $offset, 'caps');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return Response
     *
     * @throws \Exception
     */
    public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
    {
        $this->type = $type;

        return $this->presenter->output($data, $params, $xml, $offset, $type, $headers);
    }

    /**
     * Collect and return various capability information for usage in API.
     * Cached for 10 minutes to avoid repeated Settings DB lookups on every API response.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getForMenu(): array
    {
        $includeCats = $this->type === 'caps';

        return $this->capabilitiesService->v1($includeCats);
    }

    /**
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response|int
     */
    public function maxAge(Request $request)
    {
        $maxAge = -1;
        if ($request->has('maxage')) {
            if (! $request->filled('maxage')) {
                return showApiError(201, 'Incorrect parameter (maxage must not be empty)');
            } elseif (! is_numeric($request->input('maxage'))) {
                return showApiError(201, 'Incorrect parameter (maxage must be numeric)');
            } else {
                $maxAge = (int) $request->input('maxage');
            }
        }

        return $maxAge;
    }

    /**
     * Verify cat parameter.
     *
     * @return array<int, string|int>
     */
    public function categoryID(Request $request): array
    {
        return $this->parameters()->categories($request);
    }

    /**
     * Verify groupName parameter.
     *
     * @throws \Exception
     */
    public function group(Request $request): string|int|bool
    {
        return $this->parameters()->group($request);
    }

    /**
     * Verify limit parameter.
     */
    public function limit(Request $request): int
    {
        return $this->parameters()->limit($request);
    }

    /**
     * Verify offset parameter.
     */
    public function offset(Request $request): int
    {
        return $this->parameters()->offset($request);
    }

    /**
     * Validate and normalize the API sort parameter.
     *
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response|string
     */
    public function sort(Request $request)
    {
        $defaultSort = 'posted_desc';
        if (! $request->has('sort')) {
            return $defaultSort;
        }

        $sort = strtolower(trim((string) $request->input('sort')));
        if ($sort === '') {
            return showApiError(201, 'Incorrect parameter (sort must not be empty)');
        }

        if (! preg_match('/^(cat|name|size|files|stats|posted)_(asc|desc)$/', $sort)) {
            return showApiError(201, 'Incorrect parameter (sort must be one of: cat_asc/desc, name_asc/desc, size_asc/desc, files_asc/desc, stats_asc/desc, posted_asc/desc)');
        }

        return $sort;
    }

    /**
     * Check if a parameter is empty.
     *
     * @return Response|void
     */
    public function verifyEmptyParameter(Request $request, string $parameter)
    {
        if ($request->has($parameter) && $request->isNotFilled($parameter)) {
            return showApiError(201, 'Incorrect parameter ('.$parameter.' must not be empty)');
        }
    }

    private function hasMovieSearchParameters(Request $request): bool
    {
        return $request->filled('q')
            || $request->filled('imdbid')
            || $request->filled('tmdbid')
            || $request->filled('traktid');
    }

    private function hasTvSearchParameters(Request $request): bool
    {
        return $request->filled('q')
            || $request->filled('vid')
            || $request->filled('tvdbid')
            || $request->filled('traktid')
            || $request->filled('rid')
            || $request->filled('tvmazeid')
            || $request->filled('imdbid')
            || $request->filled('tmdbid');
    }

    /**
     * Get cached user stats (API requests + download counts/timestamps) in a single query.
     * Cached for 60 seconds to reduce DB hits across rapid API calls.
     */
    public function getCachedUserStats(int $userId): object
    {
        return $this->usage()->statistics($userId);
    }

    private function parameters(): ApiQueryParameters
    {
        return $this->queryParameters ?? new ApiQueryParameters;
    }

    private function usage(): ApiUsageService
    {
        return $this->usageService;
    }

    public function addCoverURL(mixed &$releases, callable $getCoverURL): void
    {
        if ($releases && \count($releases)) {
            foreach ($releases as $key => $release) {
                if (isset($release->id)) {
                    $release->coverurl = $getCoverURL($release);
                }
            }
        }
    }
}
