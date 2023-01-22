<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use App\Http\Controllers\Api\API;
use Blacklight\Releases;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiController extends BasePageController
{
    /**
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws \Throwable
     */
    public function api(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
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

        $uid = $apiKey = $oldestGrabTime = $thisOldestTime = '';
        $res = $catExclusions = [];
        $maxRequests = $thisRequests = $maxDownloads = $grabs = 0;

        // Page is accessible only by the apikey

        if ($function !== 'c' && $function !== 'r') {
            if ($request->missing('apikey') || ($request->has('apikey') && empty($request->input('apikey')))) {
                Utility::showApiError(200, 'Missing parameter (apikey)');
            } else {
                $apiKey = $request->input('apikey');
                $res = User::getByRssToken($apiKey);
                if ($res === null) {
                    Utility::showApiError(100, 'Incorrect user credentials (wrong API key)');
                }
            }

            if ($res->hasRole('Disabled')) {
                Utility::showApiError(101);
            }

            $uid = $res['id'];
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
            event(new UserAccessedApi($res));
            $thisRequests = UserRequest::getApiRequests($uid);
            $grabs = UserDownload::getDownloadRequests($uid);
            if ($thisRequests > $maxRequests) {
                Utility::showApiError(500, 'Request limit reached ('.$thisRequests.'/'.$maxRequests.')');
            }
        }

        $releases = new Releases();
        $api = new API(['Settings' => $this->settings, 'Request' => $request]);

        // Set Query Parameters based on Request objects
        $outputXML = ! ($request->has('o') && $request->input('o') === 'json');
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $offset = $api->offset();

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
               $api->verifyEmptyParameter('q');
               $maxAge = $api->maxAge();
               $groupName = $api->group();
               UserRequest::addApiRequest($apiKey, $request->getRequestUri());
               $categoryID = $api->categoryID();
               $limit = $api->limit();
               $searchArr = [
                   'searchname' => $request->input('q') ?? -1,
                   'name' => -1,
                   'fromname' => -1,
                   'filename' => -1,
               ];

               if ($request->has('q')) {
                   $relData = $releases->search(
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
                   $relData = $releases->getBrowseRange(
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
               UserRequest::addApiRequest($apiKey, $request->getRequestUri());

               $siteIdArr = [
                   'id'     => $request->input('vid') ?? '0',
                   'tvdb'   => $request->input('tvdbid') ?? '0',
                   'trakt'  => $request->input('traktid') ?? '0',
                   'tvrage' => $request->input('rid') ?? '0',
                   'tvmaze' => $request->input('tvmazeid') ?? '0',
                   'imdb'   => Str::replace('tt', '', $request->input('imdbid')) ?? '0',
                   'tmdb'   => $request->input('tmdbid') ?? '0',
               ];

               // Process season only queries or Season and Episode/Airdate queries

               $series = $request->input('season') ?? '';
               $episode = $request->input('ep') ?? '';

               if (preg_match('#^(19|20)\d{2}$#', $series, $year) && str_contains($episode, '/')) {
                   $airDate = str_replace('/', '-', $year[0].'-'.$episode);
               }

               $relData = $releases->tvSearch(
                   $siteIdArr,
                   $series,
                   $episode,
                   $airDate ?? '',
                   $api->offset(),
                   $api->limit(),
                   $request->input('q') ?? '',
                   $api->categoryID(),
                   $maxAge,
                   $minSize,
                   $catExclusions
               );

               $api->output($relData, $params, $outputXML, $offset, 'api');
               break;

           // Search movie releases.
           case 'm':
               $api->verifyEmptyParameter('q');
               $api->verifyEmptyParameter('imdbid');
               $maxAge = $api->maxAge();
               UserRequest::addApiRequest($apiKey, $request->getRequestUri());

               $imdbId = $request->has('imdbid') && ! empty($request->input('imdbid')) ? (int) $request->input('imdbid') : -1;
               $tmdbId = $request->has('tmdbid') && ! empty($request->input('tmdbid')) ? (int) $request->input('tmdbid') : -1;
               $traktId = $request->has('traktid') && ! empty($request->input('traktid')) ? (int) $request->input('traktid') : -1;

               $relData = $releases->moviesSearch(
                   $imdbId,
                   $tmdbId,
                   $traktId,
                   $api->offset(),
                   $api->limit(),
                   $request->input('q') ?? '',
                   $api->categoryID(),
                   $maxAge,
                   $minSize,
                   $catExclusions
               );

               $api->addCoverURL(
                   $relData,
                   function ($release) {
                       return Utility::getCoverURL(['type' => 'movies', 'id' => $release->imdbid]);
                   }
               );

               $api->output($relData, $params, $outputXML, $offset, 'api');
               break;

           // Get NZB.
           case 'g':
               $api->verifyEmptyParameter('g');
               UserRequest::addApiRequest($apiKey, $request->getRequestUri());
               $relData = Release::checkGuidForApi($request->input('id'));
               if ($relData) {
                   return redirect(url('/getnzb?r='.$apiKey.'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : '')));
               }

               Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
               break;

           // Get individual NZB details.
           case 'd':
               if ($request->missing('id')) {
                   Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
               }

               UserRequest::addApiRequest($apiKey, $request->getRequestUri());
               $data = Release::getByGuid($request->input('id'));

               $api->output($data, $params, $outputXML, $offset, 'api');
               break;

           // Get an NFO file for an individual release.
           case 'n':
               if ($request->missing('id')) {
                   Utility::showApiError(200, 'Missing parameter (id is required for retrieving an NFO)');
               }

               UserRequest::addApiRequest($apiKey, $request->getRequestUri());
               $rel = Release::query()->where('guid', $request->input('id'))->first(['id', 'searchname']);
               $data = ReleaseNfo::getReleaseNfo($rel['id']);

               if ($rel !== null) {
                   if ($data !== null) {
                       if ($request->has('o') && $request->input('o') === 'file') {
                           return response()->streamDownload(function () use ($data) {
                               echo $data['nfo'];
                           }, $rel['searchname'].'.nfo', ['Content-type:' => 'application/octet-stream']);
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
               $api->output([], $params, true, $offset, 'caps');
               break;
       }
    }
}
