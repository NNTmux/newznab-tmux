<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\Releases\ReleaseSearchService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            $res = User::getByRssToken($apiKey);
            if ($res === null) {
                return showApiError(100, 'Incorrect user credentials (wrong API key)');
            }

            if ($res->hasRole('Disabled')) {
                return showApiError(101);
            }

            $uid = $res->id;
            $catExclusions = User::getCategoryExclusionForApi($request);
            $maxRequests = $res->role->apirequests;
            $maxDownloads = $res->role->downloadrequests;
            $time = UserRequest::whereUsersId($uid)->min('timestamp');
            $thisOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
            $grabTime = UserDownload::whereUsersId($uid)->min('timestamp');
            $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';
        }

        // Record user access to the api, if its been called by a user (i.e. capabilities request do not require a user to be logged in or key provided).
        if ($uid !== '') {
            event(new UserAccessedApi($res, $request->ip()));
            $thisRequests = UserRequest::getApiRequests($uid);
            $grabs = UserDownload::getDownloadRequests($uid);
            if ($thisRequests > $maxRequests) {
                return showApiError(500, 'Request limit reached ('.$thisRequests.'/'.$maxRequests.')');
            }
        }

        // Set Query Parameters based on Request objects
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
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
                $this->verifyEmptyParameter($request, 'q');
                $maxAge = $this->maxAge($request);
                $groupName = $this->group($request);
                UserRequest::addApiRequest($apiKey, $request->getRequestUri());
                $categoryID = $this->categoryID($request);
                $limit = $this->limit($request);
                $searchArr = [
                    'searchname' => $request->input('q') ?? -1,
                    'name' => -1,
                    'fromname' => -1,
                    'filename' => -1,
                ];

                if ($request->has('q')) {
                    $relData = $this->releaseSearchService->search(
                        $searchArr,
                        $groupName,
                        -1,
                        -1,
                        -1,
                        -1,
                        $offset,
                        $limit,
                        '',
                        $maxAge,
                        $catExclusions,
                        'basic',
                        $categoryID,
                        $minSize
                    );
                } else {
                    $relData = $this->releaseBrowseService->getBrowseRange(
                        1,
                        $categoryID,
                        $offset,
                        $limit,
                        '',
                        $maxAge,
                        $catExclusions,
                        $groupName,
                        $minSize
                    );
                }
                $this->output($relData, $params, $outputXML, $offset, 'api');
                break;
                // Search tv releases.
            case 'tv':
                $this->verifyEmptyParameter($request, 'q');
                $this->verifyEmptyParameter($request, 'vid');
                $this->verifyEmptyParameter($request, 'tvdbid');
                $this->verifyEmptyParameter($request, 'traktid');
                $this->verifyEmptyParameter($request, 'rid');
                $this->verifyEmptyParameter($request, 'tvmazeid');
                $this->verifyEmptyParameter($request, 'imdbid');
                $this->verifyEmptyParameter($request, 'tmdbid');
                $this->verifyEmptyParameter($request, 'season');
                $this->verifyEmptyParameter($request, 'ep');
                $maxAge = $this->maxAge($request);
                UserRequest::addApiRequest($apiKey, $request->getRequestUri());

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
                    $this->limit($request),
                    $request->input('q') ?? '',
                    $this->categoryID($request),
                    $maxAge,
                    $minSize,
                    $catExclusions
                );

                $this->output($relData, $params, $outputXML, $offset, 'api');
                break;

                // Search movie releases.
            case 'm':
                $this->verifyEmptyParameter($request, 'q');
                $this->verifyEmptyParameter($request, 'imdbid');
                $maxAge = $this->maxAge($request);
                UserRequest::addApiRequest($apiKey, $request->getRequestUri());

                $imdbId = $request->has('imdbid') && $request->filled('imdbid') ? (int) $request->input('imdbid') : -1;
                $tmdbId = $request->has('tmdbid') && $request->filled('tmdbid') ? (int) $request->input('tmdbid') : -1;
                $traktId = $request->has('traktid') && $request->filled('traktid') ? (int) $request->input('traktid') : -1;

                $relData = $this->releaseSearchService->moviesSearch(
                    $imdbId,
                    $tmdbId,
                    $traktId,
                    $this->offset($request),
                    $this->limit($request),
                    $request->input('q') ?? '',
                    $this->categoryID($request),
                    $maxAge,
                    $minSize,
                    $catExclusions
                );

                $this->addCoverURL(
                    $relData,
                    function ($release) {
                        return getCoverURL(['type' => 'movies', 'id' => $release->imdbid]);
                    }
                );

                $this->output($relData, $params, $outputXML, $offset, 'api');
                break;

                // Get NZB.
            case 'g':
                $this->verifyEmptyParameter($request, 'g');
                UserRequest::addApiRequest($apiKey, $request->getRequestUri());
                $relData = Release::checkGuidForApi($request->input('id'));
                if ($relData) {
                    return redirect(url('/getnzb?r='.$apiKey.'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : '')));
                }

                return showApiError(300, 'No such item (the guid you provided has no release in our database)');

                // Get individual NZB details.
            case 'd':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (guid is required for single release details)');
                }

                UserRequest::addApiRequest($apiKey, $request->getRequestUri());
                $data = Release::getByGuid($request->input('id'));

                $this->output($data, $params, $outputXML, $offset, 'api');
                break;

                // Get an NFO file for an individual release.
            case 'n':
                if ($request->missing('id')) {
                    return showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
                }

                UserRequest::addApiRequest($apiKey, $request->getRequestUri());
                $rel = Release::query()->where('guid', $request->input('id'))->first(['id', 'searchname']);

                if ($rel) {
                    $data = ReleaseNfo::getReleaseNfo($rel->id);
                    if (! empty($data)) {
                        if ($request->has('o') && $request->input('o') === 'file') {
                            return response()->streamDownload(function () use ($data) {
                                echo $data['nfo'];
                            }, $rel['searchname'].'.nfo', ['Content-type:' => 'application/octet-stream']);
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
                // nzb add request
                // curl -X POST -F "file=@./The.File.nzb" "site_url/api/V1/api?t=nzbadd&apikey=xxx"
                //
            case 'nzbAdd':
                if (! User::canPost($uid)) {
                    return response('User does not have permission to post', 403);
                }

                if ($request->missing('file')) {
                    return response('Missing parameter (file is required for adding an NZB)', 400);
                }
                if ($request->missing('apikey')) {
                    return response('Missing parameter (apikey is required for adding an NZB)', 400);
                }

                if (! $request->hasFile('file')) {
                    return response('Missing parameter (file is required for adding an NZB)', 400);
                }

                UserRequest::addApiRequest($apiKey, $request->getRequestUri());

                $nzbFile = $request->file('file');

                // Save the file to the server, get the name without the extension.
                if (File::isFile($nzbFile)) {
                    // We need to check if file is an actual nzb file.
                    if ($nzbFile->getClientOriginalExtension() !== 'nzb') {
                        return response('File is not an NZB file', 400);
                    }
                    // Check if the file is proper xml nzb file.
                    if (! isValidNewznabNzb($nzbFile->getContent())) {
                        return response('File is not a valid Newznab NZB file', 400);
                    }
                    if (! File::isDirectory(config('nntmux.nzb_upload_folder'))) {
                        @File::makeDirectory(config('nntmux.nzb_upload_folder'), 0775, true);
                    }

                    if (File::put(config('nntmux.nzb_upload_folder').$nzbFile->getClientOriginalName(), $nzbFile->getContent())) {
                        Log::channel('nzb_upload')->info('NZB file uploaded by API: '.$nzbFile->getClientOriginalName());

                        return response('NZB file uploaded successfully', 200);
                    }

                    Log::channel('nzb_upload')->warning('NZB file uploaded by API failed: '.$nzbFile->getClientOriginalName());
                } else {
                    Log::channel('nzb_upload')->warning('NZB file uploaded by API failed: '.$nzbFile->getClientOriginalName());

                    return response('NZB file upload failed', 500);
                }

                break;

                // Capabilities request.
            case 'c':
                $this->output([], $params, $outputXML, $offset, 'caps');
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Response|void
     *
     * @throws \Exception
     */
    public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '')
    {
        $this->type = $type;
        $options = [
            'Parameters' => $params,
            'Data' => $data,
            'Server' => $this->getForMenu(),
            'Offset' => $offset,
            'Type' => $type,
        ];

        // Generate the XML Response
        $response = (new XML_Response($options))->returnXML();

        if ($xml) {
            header('Content-type: text/xml');
        } else {
            // JSON encode the XMLWriter response
            $response = json_encode(xml_to_array($response), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
            header('Content-type: application/json');
        }
        if ($response === false) {
            return showApiError(201);
        } else {
            header('Content-Length: '.\strlen($response));
            echo $response;
            exit;
        }
    }

    /**
     * Collect and return various capability information for usage in API.
     *
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getForMenu(): array
    {
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
            'registration' => [
                'available' => 'yes',
                'open' => (int) Settings::settingValue('registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search' => ['available' => 'yes', 'supportedParams' => 'q'],
                'tv-search' => ['available' => 'yes', 'supportedParams' => 'q,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'q,imdbid, tmdbid, traktid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => $this->type === 'caps'
                ? Category::getForMenu()
                : null,
        ];
    }

    /**
     * @return Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Foundation\Application|Response|int
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
        $categoryID[] = -1;
        if ($request->has('cat')) {
            $categoryIDs = urldecode($request->input('cat'));
            // Append Web-DL category ID if HD present for SickBeard / Sonarr compatibility.
            if (str_contains($categoryIDs, (string) Category::TV_HD) && ! str_contains($categoryIDs, (string) Category::TV_WEBDL) && (int) Settings::settingValue('catwebdl') === 0) {
                $categoryIDs .= (','.Category::TV_WEBDL);
            }
            $categoryID = explode(',', $categoryIDs);
        }

        return $categoryID;
    }

    /**
     * Verify groupName parameter.
     *
     *
     * @return list<int|string>
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
