<?php

use App\Models\User;
use App\Models\Release;
use Blacklight\http\API;
use Blacklight\Releases;
use App\Models\ReleaseNfo;
use App\Models\UserRequest;
use Blacklight\utility\Utility;

// API functions.
$function = 's';
if ($page->request->has('t')) {
    switch ($page->request->input('t')) {
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
            $function = 's';
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
        default:
            Utility::showApiError(202, 'No such function ('.$page->request->input('t').')');
    }
} else {
    Utility::showApiError(200, 'Missing parameter (t)');
}

$uid = $apiKey = '';
$res = $catExclusions = [];
$maxRequests = 0;

// Page is accessible only by the apikey

if ($function !== 'c' && $function !== 'r') {
    if (! $page->request->has('apikey')) {
        Utility::showApiError(200, 'Missing parameter (apikey)');
    } else {
        $apiKey = $page->request->input('apikey');
        $res = User::getByRssToken($apiKey);
        if ($res === null) {
            Utility::showApiError(100, 'Incorrect user credentials (wrong API key)');
        }
    }

    if (User::isDisabled($res['username'])) {
        Utility::showApiError(101);
    }

    $uid = $res['id'];
    $catExclusions = User::getCategoryExclusion($uid);
    $maxRequests = $res->role->apirequests;
}

// Record user access to the api, if its been called by a user (i.e. capabilities request do not require a user to be logged in or key provided).
if ($uid !== '') {
    User::updateApiAccessed($uid);
    $apiRequests = UserRequest::getApiRequests($uid);
    if ($apiRequests > $maxRequests) {
        Utility::showApiError(500, 'Request limit reached ('.$apiRequests.'/'.$maxRequests.')');
    }
}

$releases = new Releases(['Settings' => $page->settings]);
$api = new API(['Settings' => $page->settings, 'Request' => $_GET]);

// Set Query Parameters based on Request objects
$outputXML = ! ($page->request->has('o') && $page->request->input('o') === 'json');
$minSize = ($page->request->has('minsize') && $page->request->input('minsize') > 0 ? $page->request->input('minsize') : 0);
$offset = $api->offset();

// Set API Parameters based on Request objects
$params['extended'] = ($page->request->has('extended') && (int) $page->request->input('extended') === 1 ? '1' : '0');
$params['del'] = ($page->request->has('del') && (int) $page->request->input('del') === 1 ? '1' : '0');
$params['uid'] = $uid;
$params['token'] = $apiKey;

switch ($function) {
    // Search releases.
    case 's':
        $api->verifyEmptyParameter('q');
        $maxAge = $api->maxAge();
        $groupName = $api->group();
        UserRequest::addApiRequest($uid, $page->request->getRequestUri());
        $categoryID = $api->categoryID();
        $limit = $api->limit();

        if ($page->request->has('q')) {
            $relData = $releases->search(
                $page->request->input('q'),
                -1,
                -1,
                -1,
                $groupName,
                -1,
                -1,
                0,
                0,
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
            $relData = $releases->getBrowseRange(
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
        $api->output($relData, $params, $outputXML, $offset, 'api');
        break;
    // Search tv releases.
    case 'tv':
        $api->verifyEmptyParameter('q');
        $api->verifyEmptyParameter('vid');
        $api->verifyEmptyParameter('tvdbid');
        $api->verifyEmptyParameter('traktid');
        $api->verifyEmptyParameter('rid');
        $api->verifyEmptyParameter('tvmazeid');
        $api->verifyEmptyParameter('imdbid');
        $api->verifyEmptyParameter('tmdbid');
        $api->verifyEmptyParameter('season');
        $api->verifyEmptyParameter('ep');
        $maxAge = $api->maxAge();
        UserRequest::addApiRequest($uid, $page->request->getRequestUri());

        $siteIdArr = [
            'id'     => $page->request->input('vid') ?? '0',
            'tvdb'   => $page->request->input('tvdbid') ?? '0',
            'trakt'  => $page->request->input('traktid') ?? '0',
            'tvrage' => $page->request->input('rid') ?? '0',
            'tvmaze' => $page->request->input('tvmazeid') ?? '0',
            'imdb'   => $page->request->input('imdbid') ?? '0',
            'tmdb'   => $page->request->input('tmdbid') ?? '0',
        ];

        // Process season only queries or Season and Episode/Airdate queries

        $series = $page->request->input('season') ?? '';
        $episode = $page->request->input('ep') ?? '';

        if (preg_match('#^(19|20)\d{2}$#', $series, $year) && strpos($episode, '/') !== false) {
            $airdate = str_replace('/', '-', $year[0].'-'.$episode);
        }

        $relData = $releases->tvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airdate ?? '',
            $offset,
            $api->limit(),
            $page->request->input('q') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize
        );

        $api->addLanguage($relData);
        $api->output($relData, $params, $outputXML, $offset, 'api');
        break;

    // Search movie releases.
    case 'm':
        $api->verifyEmptyParameter('q');
        $api->verifyEmptyParameter('imdbid');
        $maxAge = $api->maxAge();
        UserRequest::addApiRequest($uid, $page->request->getRequestUri());

        $imdbId = ($page->request->input('imdbid') ?? -1);

        $relData = $releases->moviesSearch(
            $imdbId,
            $offset,
            $api->limit(),
            $page->request->input('q') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize
        );

        $api->addCoverURL(
            $relData,
            function ($release) {
                return Utility::getCoverURL(['type' => 'movies', 'id' => $release['imdbid']]);
            }
        );

        $api->addLanguage($relData);
        $api->output($relData, $params, $outputXML, $offset, 'api');
        break;

    // Get NZB.
    case 'g':
        $api->verifyEmptyParameter('g');
        UserRequest::addApiRequest($uid, $page->request->getRequestUri());
        $relData = Release::checkGuidForApi($page->request->input('id'));
        if ($relData !== false) {
            header(
                'Location:'.
                WWW_TOP.
                '/getnzb?i='.
                $uid.
                '&r='.
                $apiKey.
                '&id='.
                $page->request->input('id').
                (($page->request->has('del') && $page->request->input('del') === '1') ? '&del=1' : '')
            );
        } else {
            Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
        }
        break;

    // Get individual NZB details.
    case 'd':
        if (! $page->request->has('id')) {
            Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
        }

        UserRequest::addApiRequest($uid, $page->request->getRequestUri());
        $data = Release::getByGuid($page->request->input('id'));

        $relData = [];
        if ($data) {
            $relData[] = $data;
        }

        $api->output($relData, $params, $outputXML, $offset, 'api');
        break;

    // Get an NFO file for an individual release.
    case 'n':
        if (! $page->request->has('id')) {
            Utility::showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
        }

        UserRequest::addApiRequest($uid, $page->request->getRequestUri());
        $rel = Release::query()->where('guid', $page->request->input('id'))->first(['id', 'searchname']);
        $data = ReleaseNfo::getReleaseNfo($rel['id']);

        if ($rel !== null) {
            if ($data !== null) {
                if ($page->request->has('o') && $page->request->input('o') === 'file') {
                    header('Content-type: application/octet-stream');
                    header("Content-disposition: attachment; filename={$rel['searchname']}.nfo");
                    exit($data['nfo']);
                }

                echo nl2br(Utility::cp437toUTF($data['nfo']));
            } else {
                Utility::showApiError(300, 'Release does not have an NFO file associated.');
            }
        } else {
            Utility::showApiError(300, 'Release does not exist.');
        }
        break;

    // Capabilities request.
    case 'c':
        $api->output([], $params, $outputXML, $offset, 'caps');
        break;
}
