<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\RegistrationStatusService;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use App\Support\FilenameSanitizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiController extends BasePageController
{
    private string $type;

    protected ReleaseSearchService $releaseSearchService;

    protected ReleaseBrowseService $releaseBrowseService;

    public function __construct(
        ReleaseSearchService $releaseSearchService,
        ReleaseBrowseService $releaseBrowseService
    ) {
        parent::__construct();
        $this->releaseSearchService = $releaseSearchService;
        $this->releaseBrowseService = $releaseBrowseService;
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
            $userCacheKey = 'api_user:'.md5((string) $apiKey);
            $res = Cache::remember($userCacheKey, 300, function () use ($apiKey) {
                return User::verifiedApiTokenQuery($apiKey)
                    ->with('role')
                    ->first();
            });

            if ($res === null) {
                return showApiError(100, 'Incorrect user credentials (wrong API key)');
            }

            if ($res->is_disabled || $res->hasRole('Disabled')) {
                return showApiError(101);
            }

            $uid = $res->id;
            // Use user ID directly instead of re-looking up by token
            $catExclusions = User::getCategoryExclusionById($uid);
            $maxRequests = $res->role->apirequests;
            $maxDownloads = $res->role->downloadrequests;

            // Consolidated user stats: single query with 60s cache instead of 4 separate queries
            $userStats = $this->getCachedUserStats($uid);
            $thisOldestTime = $userStats->api_time ? Carbon::parse($userStats->api_time)->toRfc2822String() : '';
            $oldestGrabTime = $userStats->grab_time ? Carbon::parse($userStats->grab_time)->toRfc2822String() : '';
            $thisRequests = (int) ($userStats->api_count ?? 0);
            $grabs = (int) ($userStats->grab_count ?? 0);
        }

        // Record user access to the api, if its been called by a user (i.e. capabilities request do not require a user to be logged in or key provided).
        if ($uid !== '') {
            event(new UserAccessedApi($res, $request->ip()));
            if ($thisRequests > $maxRequests) {
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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if ($request->has('q')) {
                    $relData = $this->releaseSearchService->apiSearch(
                        $request->input('q'),
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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $this->hasTvSearchParameters($request)) {
                    if ($categoryID === [-1]) {
                        $categoryID = Category::TV_GROUP;
                    }

                    $relData = $this->releaseBrowseService->getBrowseRangeForApi(
                        1,
                        $categoryID,
                        $offset,
                        $limit,
                        $sort,
                        $maxAge,
                        $catExclusions,
                        -1,
                        $minSize
                    );

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

                $relData = $this->releaseSearchService->tvSearch(
                    $siteIdArr,
                    $series,
                    $episode,
                    $airDate ?? '',
                    $this->offset($request),
                    $limit,
                    $request->input('q') ?? '',
                    $categoryID,
                    $maxAge,
                    $minSize,
                    $catExclusions,
                    $sort
                );

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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $this->hasMovieSearchParameters($request)) {
                    if ($categoryID === [-1]) {
                        $categoryID = Category::MOVIES_GROUP;
                    }

                    $relData = $this->releaseBrowseService->getBrowseRangeForApi(
                        1,
                        $categoryID,
                        $offset,
                        $limit,
                        $sort,
                        $maxAge,
                        $catExclusions,
                        -1,
                        $minSize
                    );

                    return $this->output($relData, $params, $outputXML, $offset, 'api');
                }

                $imdbId = $request->has('imdbid') && $request->filled('imdbid')
                    ? (string) Str::replace('tt', '', (string) $request->input('imdbid'))
                    : '';
                $tmdbId = $request->has('tmdbid') && $request->filled('tmdbid') ? (int) $request->input('tmdbid') : -1;
                $traktId = $request->has('traktid') && $request->filled('traktid') ? (int) $request->input('traktid') : -1;

                $relData = $this->releaseSearchService->moviesSearch(
                    $imdbId,
                    $tmdbId,
                    $traktId,
                    $this->offset($request),
                    $limit,
                    $request->input('q') ?? '',
                    $categoryID,
                    $maxAge,
                    $minSize,
                    $catExclusions,
                    $sort
                );

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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $request->filled('q')) {
                    if ($categoryID === [-1]) {
                        $categoryID = [Category::MUSIC_ROOT];
                    }

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
                } else {
                    $relData = $this->releaseSearchService->apiMusicSearch(
                        (string) $request->input('q'),
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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);

                if (! $request->filled('q')) {
                    if ($categoryID === [-1]) {
                        $categoryID = [Category::BOOKS_ROOT];
                    }

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
                } else {
                    $relData = $this->releaseSearchService->apiBookSearch(
                        (string) $request->input('q'),
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
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $relData = $this->releaseSearchService->animeSearch(
                    $anidb,
                    $offset,
                    $this->limit($request),
                    $q,
                    $this->categoryID($request),
                    $maxAge,
                    $catExclusions,
                    $anilist,
                    $sort
                );

                return $this->output($relData, $params, $outputXML, $offset, 'api');

                // Get NZB.
            case 'g':
                $emptyParameterError = $this->verifyEmptyParameter($request, 'g');
                if ($emptyParameterError !== null) {
                    return $emptyParameterError;
                }
                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $relData = Release::checkGuidForApi($request->input('id'));
                if ($relData) {
                    return redirect(url('/getnzb?r='.rawurlencode((string) $apiKey).'&id='.rawurlencode((string) $request->input('id')).(($request->has('del') && $request->input('del') === '1') ? '&del=1' : '')));
                }

                return showApiError(300, 'No such item (the guid you provided has no release in our database)');

                // Get individual NZB details.
            case 'd':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (guid is required for single release details)');
                }

                UserRequest::addApiRequest($uid, $request->getRequestUri());
                $data = Release::getByGuidForApi($request->input('id'));

                return $this->output($data, $params, $outputXML, $offset, 'api');

                // Get an NFO file for an individual release.
            case 'n':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
                }

                UserRequest::addApiRequest($uid, $request->getRequestUri());
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
                // nzb / nfo add request
                // curl -X POST -F "file=@./The.File.nzb" "site_url/api/V1/api?t=nzbadd&apikey=xxx"
                // curl -X POST -F "file=@./The.File.nfo" "site_url/api/V1/api?t=nzbadd&apikey=xxx"
                //
            case 'nzbAdd':
                if (! User::canPost($uid)) {
                    return showApiError(102, 'Insufficient privileges/not authorized');
                }

                if ($request->missing('file')) {
                    return showApiError(200, 'Missing parameter (file is required for adding an NZB or NFO)');
                }
                if ($request->missing('apikey')) {
                    return showApiError(200, 'Missing parameter (apikey is required for adding an NZB or NFO)');
                }

                if (! $request->hasFile('file')) {
                    return showApiError(600, 'Failed to load upload (no file)');
                }

                UserRequest::addApiRequest($uid, $request->getRequestUri());

                $nzbFile = $request->file('file');

                // Save the file to the server, get the name without the extension.
                if ($nzbFile !== null) {
                    $ext = strtolower((string) $nzbFile->getClientOriginalExtension());
                    if (! in_array($ext, ['nzb', 'nfo'], true)) {
                        return showApiError(600, 'Failed to load NZB (file is not an NZB or NFO file)');
                    }

                    $content = $nzbFile->getContent();
                    if (! is_string($content)) {
                        return showApiError(600, 'Failed to load upload');
                    }

                    if ($ext === 'nzb' && ! isValidNewznabNzb($content)) {
                        return showApiError(600, 'Failed to load NZB (invalid NZB payload)');
                    }

                    // Same max raw size as NfoService::MAX_NFO_SIZE (64KB).
                    $maxNfoUploadBytes = 65535;
                    if ($ext === 'nfo' && ($content === '' || strlen($content) > $maxNfoUploadBytes)) {
                        return showApiError(600, 'Failed to load NFO (empty or too large)');
                    }

                    if (! File::isDirectory(config('nntmux.nzb_upload_folder'))) {
                        @File::makeDirectory(config('nntmux.nzb_upload_folder'), 0775, true);
                    }

                    if (File::put(config('nntmux.nzb_upload_folder').$nzbFile->getClientOriginalName(), $content)) {
                        Log::channel('nzb_upload')->info('File uploaded by API: '.$nzbFile->getClientOriginalName().' (type: '.$ext.')');

                        $successXml = sprintf(
                            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<success id=\"0\" guid=\"\" categoryid=\"%s\" name=\"%s\" />\n",
                            (string) $request->input('cat', ''),
                            htmlspecialchars(pathinfo($nzbFile->getClientOriginalName(), PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8')
                        );

                        return response($successXml, 200)->header('Content-type', 'text/xml');
                    }

                    Log::channel('nzb_upload')->warning('File upload by API failed to write: '.$nzbFile->getClientOriginalName().' (type: '.$ext.')');
                } else {
                    Log::channel('nzb_upload')->warning('File upload by API failed: no file provided');

                    return showApiError(603, 'Failed to write file to disk');
                }

                return showApiError(603, 'Failed to write file to disk');

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
        $options = [
            'Parameters' => $params,
            'Data' => $data,
            'Server' => $this->getForMenu(),
            'Offset' => $offset,
            'Type' => $type,
        ];

        $xmlResponse = new XML_Response($options);

        if ($xml) {
            $response = $xmlResponse->returnXML();
            $contentType = 'text/xml';
        } else {
            $arrayData = $xmlResponse->returnArray();
            if ($arrayData === false) {
                return showApiError(201);
            }
            $response = json_encode($arrayData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $contentType = 'application/json';
        }
        if ($response === false) {
            return showApiError(201);
        }

        return response($response, 200, array_merge([
            'Content-type' => $contentType,
            'Content-Length' => (string) \strlen($response),
        ], $headers));
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

        // Cache the server info blob (without categories) for 10 minutes
        $serverInfo = Cache::remember('api_v1_server_menu', 600, function () {
            $serverroot = url('/');

            return [
                'server' => [
                    'title' => config('app.name'),
                    'strapline' => Settings::settingValue('strapline'),
                    'email' => config('mail.from.address'),
                    'meta' => Settings::settingValue('metakeywords'),
                    'url' => $serverroot,
                    'image' => $serverroot.'/assets/images/tmux_logo.png',
                ],
                'limits' => [
                    'max' => 100,
                    'default' => 100,
                ],
                'searching' => [
                    'search' => ['available' => 'yes', 'supportedParams' => 'q,group,minsize,maxsize,maxage,cat,limit,offset,attrs,extended,del,sort'],
                    'tv-search' => ['available' => 'yes', 'supportedParams' => 'q,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep,cat,minsize,maxsize,maxage,limit,offset,attrs,extended,del,sort'],
                    'movie-search' => ['available' => 'yes', 'supportedParams' => 'q,imdbid,tmdbid,traktid,genre,cat,minsize,maxsize,maxage,limit,offset,attrs,extended,del,sort'],
                    'audio-search' => ['available' => 'yes', 'supportedParams' => 'q,cat,minsize,maxsize,maxage,group,limit,offset,attrs,extended,del,sort'],
                    'book-search' => ['available' => 'yes', 'supportedParams' => 'q,title,author,cat,minsize,maxsize,maxage,group,limit,offset,attrs,extended,del,sort'],
                    'anime-search' => ['available' => 'yes', 'supportedParams' => 'q,anidbid,anilistid,cat,minsize,maxsize,maxage,limit,offset,attrs,extended,del,sort'],
                ],
            ];
        });

        $registrationStatus = app(RegistrationStatusService::class)->resolve();
        $serverInfo['registration'] = [
            'available' => $registrationStatus['available'] ? 'yes' : 'no',
            'open' => $registrationStatus['is_open'] ? 'yes' : 'no',
        ];

        // Only load categories for caps requests (also cached via Category::getForMenu)
        $serverInfo['categories'] = $includeCats ? Category::getForMenu() : null;
        $serverInfo['groups'] = $includeCats
            ? (Schema::hasTable('usenet_groups')
                ? UsenetGroup::query()
                    ->where('active', 1)
                    ->orderBy('name')
                    ->get(['name', 'description', 'last_updated'])
                    ->map(static fn (UsenetGroup $group): array => [
                        'name' => $group->name,
                        'description' => (string) ($group->description ?? ''),
                        'lastupdate' => $group->last_updated ? Carbon::parse($group->last_updated)->toRfc2822String() : '',
                    ])
                    ->all()
                : [])
            : null;
        $serverInfo['genres'] = $includeCats
            ? (Schema::hasTable('genres')
                ? Genre::query()
                    ->enabled()
                    ->orderBy('title')
                    ->get(['id', 'title', 'type'])
                    ->map(static fn (Genre $genre): array => [
                        'id' => $genre->id,
                        'name' => $genre->title,
                        'categoryid' => (int) ($genre->type ?? 0),
                    ])
                    ->all()
                : [])
            : null;

        return $serverInfo;
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
     * @return array<string, mixed>
     */
    public function categoryID(Request $request): array
    {
        $categoryID = [-1];
        if (! $request->has('cat')) {
            return $categoryID;
        }

        $rawCategoryIDs = $request->input('cat');
        if (is_array($rawCategoryIDs)) {
            $categoryIDs = implode(',', array_values(array_filter(array_map(
                static fn (mixed $categoryId): string => urldecode(trim((string) $categoryId)),
                $rawCategoryIDs
            ), static fn (string $categoryId): bool => $categoryId !== '')));
        } elseif (is_scalar($rawCategoryIDs)) {
            $categoryIDs = urldecode(trim((string) $rawCategoryIDs));
        } else {
            return $categoryID;
        }

        if ($categoryIDs === '') {
            return $categoryID;
        }

        // Append Web-DL category ID if HD present for SickBeard / Sonarr compatibility.
        if (str_contains($categoryIDs, (string) Category::TV_HD) && ! str_contains($categoryIDs, (string) Category::TV_WEBDL) && (int) Settings::settingValue('catwebdl') === 0) {
            $categoryIDs .= (','.Category::TV_WEBDL);
        }

        $categoryID = array_values(array_filter(array_map('trim', explode(',', $categoryIDs)), static fn (string $categoryId): bool => $categoryId !== ''));

        return $categoryID;
    }

    /**
     * Verify groupName parameter.
     *
     * @throws \Exception
     */
    public function group(Request $request): string|int|bool
    {
        $groupName = -1;
        if ($request->has('group')) {
            $group = UsenetGroup::isValidGroup($request->input('group'));
            if ($group !== false) {
                $groupName = $group;
            }
        }

        return $groupName;
    }

    /**
     * Verify limit parameter.
     */
    public function limit(Request $request): int
    {
        $limit = 100;
        if ($request->has('limit') && is_numeric($request->input('limit'))) {
            $limit = (int) $request->input('limit');
        }

        return $limit;
    }

    /**
     * Verify offset parameter.
     */
    public function offset(Request $request): int
    {
        $offset = 0;
        if ($request->has('offset') && is_numeric($request->input('offset'))) {
            $offset = (int) $request->input('offset');
        }

        return $offset;
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
        $cacheKey = 'api_user_stats:'.$userId;

        return Cache::remember($cacheKey, 60, function () use ($userId) {
            $oneDayAgo = now()->subDay()->toDateTimeString();

            return DB::selectOne('
                SELECT
                    (SELECT COUNT(*) FROM user_requests WHERE users_id = ? AND timestamp > ?) as api_count,
                    (SELECT COUNT(*) FROM user_downloads WHERE users_id = ? AND timestamp > ?) as grab_count,
                    (SELECT MIN(timestamp) FROM user_requests WHERE users_id = ? AND timestamp > ?) as api_time,
                    (SELECT MIN(timestamp) FROM user_downloads WHERE users_id = ? AND timestamp > ?) as grab_time
            ', [$userId, $oneDayAgo, $userId, $oneDayAgo, $userId, $oneDayAgo, $userId, $oneDayAgo]);
        });
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
