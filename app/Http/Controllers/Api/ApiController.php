<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Release;
use Blacklight\http\API;
use Blacklight\Releases;
use App\Models\ReleaseNfo;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use App\Http\Controllers\BasePageController;

class ApiController extends BasePageController
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
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
                   Utility::showApiError(202, 'No such function ('.$request->input('t').')');
           }
        } else {
            Utility::showApiError(200, 'Missing parameter (t)');
        }

        $uid = $apiKey = '';
        $res = $catExclusions = [];
        $maxRequests = 0;

        // Page is accessible only by the apikey

        if ($function !== 'c' && $function !== 'r') {
            if (! $request->has('apikey')) {
                Utility::showApiError(200, 'Missing parameter (apikey)');
            } else {
                $apiKey = $request->input('apikey');
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

        $releases = new Releases(['Settings' => $this->settings]);
        $api = new API(['Settings' => $this->settings, 'Request' => $request]);

        // Set Query Parameters based on Request objects
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $minSize = ($request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0);
        $offset = $api->offset();

        // Set API Parameters based on Request objects
        $params['extended'] = ($request->has('extended') && (int) $request->input('extended') === 1 ? '1' : '0');
        $params['del'] = ($request->has('del') && (int) $request->input('del') === 1 ? '1' : '0');
        $params['uid'] = $uid;
        $params['token'] = $apiKey;

        switch ($function) {
           // Search releases.
           case 's':
               $api->verifyEmptyParameter('q');
               $maxAge = $api->maxAge();
               $groupName = $api->group();
               UserRequest::addApiRequest($uid, $request->getRequestUri());
               $categoryID = $api->categoryID();
               $limit = $api->limit();

               if ($request->has('q')) {
                   $relData = $releases->search(
                       $request->input('q'),
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
               UserRequest::addApiRequest($uid, $request->getRequestUri());

               $siteIdArr = [
                   'id'     => $request->input('vid') ?? '0',
                   'tvdb'   => $request->input('tvdbid') ?? '0',
                   'trakt'  => $request->input('traktid') ?? '0',
                   'tvrage' => $request->input('rid') ?? '0',
                   'tvmaze' => $request->input('tvmazeid') ?? '0',
                   'imdb'   => $request->input('imdbid') ?? '0',
                   'tmdb'   => $request->input('tmdbid') ?? '0',
               ];

               // Process season only queries or Season and Episode/Airdate queries

               $series = $request->input('season') ?? '';
               $episode = $request->input('ep') ?? '';

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
                   $request->input('q') ?? '',
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
               UserRequest::addApiRequest($uid, $request->getRequestUri());

               $imdbId = ($request->input('imdbid') ?? -1);

               $relData = $releases->moviesSearch(
                   $imdbId,
                   $offset,
                   $api->limit(),
                   $request->input('q') ?? '',
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
               UserRequest::addApiRequest($uid, $request->getRequestUri());
               $relData = Release::checkGuidForApi($request->input('id'));
               if ($relData !== false) {
                   header(
                       'Location:'.
                       WWW_TOP.
                       '/getnzb?id=i='.
                       $uid.
                       '&r='.
                       $apiKey.
                       '&id='.
                       $request->input('id').
                       (($request->has('del') && $request->input('del') === '1') ? '&del=1' : '')
                   );
               } else {
                   Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
               }
               break;

           // Get individual NZB details.
           case 'd':
               if (! $request->has('id')) {
                   Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
               }

               UserRequest::addApiRequest($uid, $request->getRequestUri());
               $data = Release::getByGuid($request->input('id'));

               $relData = [];
               if ($data) {
                   $relData[] = $data;
               }

               $api->output($relData, $params, $outputXML, $offset, 'api');
               break;

           // Get an NFO file for an individual release.
           case 'n':
               if (! $request->has('id')) {
                   Utility::showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
               }

               UserRequest::addApiRequest($uid, $request->getRequestUri());
               $rel = Release::query()->where('guid', $request->input('id'))->first(['id', 'searchname']);
               $data = ReleaseNfo::getReleaseNfo($rel['id']);

               if ($rel !== null) {
                   if ($data !== null) {
                       if ($request->has('o') && $request->input('o') === 'file') {
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
    }
}
