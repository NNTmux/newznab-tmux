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
if (isset($_GET['t'])) {
    switch ($_GET['t']) {
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
            Utility::showApiError(202, 'No such function ('.$_GET['t'].')');
    }
} else {
    Utility::showApiError(200, 'Missing parameter (t)');
}

$uid = $apiKey = '';
$res = $catExclusions = [];
$maxRequests = 0;

// Page is accessible only by the apikey

if ($function !== 'c' && $function !== 'r') {
    if (! isset($_GET['apikey'])) {
        Utility::showApiError(200, 'Missing parameter (apikey)');
    } else {
        $apiKey = $_GET['apikey'];
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
$outputXML = ! (isset($_GET['o']) && $_GET['o'] === 'json');
$minSize = (isset($_GET['minsize']) && $_GET['minsize'] > 0 ? $_GET['minsize'] : 0);
$offset = $api->offset();

// Set API Parameters based on Request objects
$params['extended'] = (isset($_GET['extended']) && (int) $_GET['extended'] === 1 ? '1' : '0');
$params['del'] = (isset($_GET['del']) && (int) $_GET['del'] === 1 ? '1' : '0');
$params['uid'] = $uid;
$params['token'] = $apiKey;

switch ($function) {
    // Search releases.
    case 's':
        $api->verifyEmptyParameter('q');
        $maxAge = $api->maxAge();
        $groupName = $api->group();
        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);
        $categoryID = $api->categoryID();
        $limit = $api->limit();

        if (isset($_GET['q'])) {
            $relData = $releases->search(
                $_GET['q'],
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
        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);

        $siteIdArr = [
            'id'     => $_GET['vid'] ?? '0',
            'tvdb'   => $_GET['tvdbid'] ?? '0',
            'trakt'  => $_GET['traktid'] ?? '0',
            'tvrage' => $_GET['rid'] ?? '0',
            'tvmaze' => $_GET['tvmazeid'] ?? '0',
            'imdb'   => $_GET['imdbid'] ?? '0',
            'tmdb'   => $_GET['tmdbid'] ?? '0',
        ];

        // Process season only queries or Season and Episode/Airdate queries

        $series = $_GET['season'] ?? '';
        $episode = $_GET['ep'] ?? '';

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
            $_GET['q'] ?? '',
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
        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);

        $imdbId = ($_GET['imdbid'] ?? -1);

        $relData = $releases->moviesSearch(
            $imdbId,
            $offset,
            $api->limit(),
            $_GET['q'] ?? '',
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
        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);
        $relData = Release::checkGuidForApi($_GET['id']);
        if ($relData !== false) {
            header(
                'Location:'.
                WWW_TOP.
                '/getnzb?i='.
                $uid.
                '&r='.
                $apiKey.
                '&id='.
                $_GET['id'].
                ((isset($_GET['del']) && $_GET['del'] === '1') ? '&del=1' : '')
            );
        } else {
            Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
        }
        break;

    // Get individual NZB details.
    case 'd':
        if (! isset($_GET['id'])) {
            Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
        }

        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);
        $data = Release::getByGuid($_GET['id']);

        $relData = [];
        if ($data) {
            $relData[] = $data;
        }

        $api->output($relData, $params, $outputXML, $offset, 'api');
        break;

    // Get an NFO file for an individual release.
    case 'n':
        if (! isset($_GET['id'])) {
            Utility::showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
        }

        UserRequest::addApiRequest($uid, $_SERVER['REQUEST_URI']);
        $rel = Release::query()->where('guid', $_GET['id'])->first(['id', 'searchname']);
        $data = ReleaseNfo::getReleaseNfo($rel['id']);

        if ($rel !== null) {
            if ($data !== null) {
                if (isset($_GET['o']) && $_GET['o'] === 'file') {
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
