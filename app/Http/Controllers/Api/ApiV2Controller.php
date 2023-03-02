<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAccessedApi;
use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use App\Transformers\ApiTransformer;
use App\Transformers\CategoryTransformer;
use App\Transformers\DetailsTransformer;
use Blacklight\Releases;
use Blacklight\utility\Utility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ApiV2Controller extends BasePageController
{
    public function capabilities(): JsonResponse
    {
        $category = Category::getForApi();

        $capabilities = [
            'server' => [
                'title' => config('app.name'),
                'strapline' => Settings::settingValue('site.main.strapline'),
                'email' => config('mail.from.address'),
                'url' => url('/'),
            ],
            'limits' => [
                'max' => 100,
                'default' => 100,
            ],
            'registration' => [
                'available' => 'no',
                'open' => (int) Settings::settingValue('..registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search' => ['available' => 'yes', 'supportedParams' => 'id'],
                'tv-search' => ['available' => 'yes', 'supportedParams' => 'id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'id, imdbid, tmdbid, traktid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => fractal($category, new CategoryTransformer()),
        ];

        return response()->json($capabilities);
    }

    /**
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     * @throws \Throwable
     */
    public function movie(Request $request): JsonResponse
    {
        $api = new API();
        $releases = new Releases();
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $maxAge = $api->maxAge();
        $catExclusions = User::getCategoryExclusionForApi($request);
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));

        $imdbId = $request->has('imdbid') && ! empty($request->input('imdbid')) ? $request->input('imdbid') : -1;
        $tmdbId = $request->has('tmdbid') && ! empty($request->input('tmdbid')) ? $request->input('tmdbid') : -1;
        $traktId = $request->has('traktid') && ! empty($request->input('traktid')) ? $request->input('traktid') : -1;
        $tags = $request->has('tags') && ! empty($request->input('tags')) ? explode(',', $request->input('tags')) : [];

        $relData = $releases->moviesSearch(
            $imdbId,
            $tmdbId,
            $traktId,
            $api->offset(),
            $api->limit(),
            $request->input('id') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize,
            $catExclusions,
            $tags
        );

        $time = UserRequest::whereUsersId($user->id)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($user->id)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $apiOldestTime,
            'grabOldestTime' => $oldestGrabTime,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function apiSearch(Request $request): JsonResponse
    {
        $api = new API();
        $releases = new Releases();
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        $offset = $api->offset();
        $catExclusions = User::getCategoryExclusionForApi($request);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $tags = $request->has('tags') && ! empty($request->input('tags')) ? explode(',', $request->input('tags')) : [];
        $maxAge = $api->maxAge();
        $groupName = $api->group();
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));
        $categoryID = $api->categoryID();
        $limit = $api->limit();

        if ($request->has('id')) {
            $relData = $releases->apiSearch(
                $request->input('id'),
                $groupName,
                $offset,
                $limit,
                $maxAge,
                $catExclusions,
                $categoryID,
                $minSize,
                $tags
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
                $minSize,
                $tags
            );
        }

        $time = UserRequest::whereUsersId($user->id)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($user->id)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $apiOldestTime,
            'grabOldestTime' => $oldestGrabTime,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function tv(Request $request): JsonResponse
    {
        $api = new API();
        $releases = new Releases();
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        $catExclusions = User::getCategoryExclusionForApi($request);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $tags = $request->has('tags') && ! empty($request->input('tags')) ? explode(',', $request->input('tags')) : [];
        $api->verifyEmptyParameter('id');
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
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        event(new UserAccessedApi($user));

        $siteIdArr = [
            'id' => $request->input('vid') ?? '0',
            'tvdb' => $request->input('tvdbid') ?? '0',
            'trakt' => $request->input('traktid') ?? '0',
            'tvrage' => $request->input('rid') ?? '0',
            'tvmaze' => $request->input('tvmazeid') ?? '0',
            'imdb' => $request->input('imdbid') ?? '0',
            'tmdb' => $request->input('tmdbid') ?? '0',
        ];

        // Process season only queries or Season and Episode/Airdate queries

        $series = $request->input('season') ?? '';
        $episode = $request->input('ep') ?? '';

        if (preg_match('#^(19|20)\d{2}$#', $series, $year) && str_contains($episode, '/')) {
            $airDate = str_replace('/', '-', $year[0].'-'.$episode);
        }

        $relData = $releases->apiTvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airDate ?? '',
            $api->offset(),
            $api->limit(),
            $request->input('id') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize,
            $catExclusions,
            $tags
        );

        $time = UserRequest::whereUsersId($user->id)->min('timestamp');
        $apiOldestTime = $time !== null ? Carbon::createFromTimeString($time)->toRfc2822String() : '';
        $grabTime = UserDownload::whereUsersId($user->id)->min('timestamp');
        $oldestGrabTime = $grabTime !== null ? Carbon::createFromTimeString($grabTime)->toRfc2822String() : '';

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'apiCurrent' => UserRequest::getApiRequests($user->id),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => UserDownload::getDownloadRequests($user->id),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $apiOldestTime,
            'grabOldestTime' => $oldestGrabTime,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function getNzb(Request $request)
    {
        $user = User::query()->where('api_token', $request->input('api_token'))->first();
        event(new UserAccessedApi($user));
        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        $relData = Release::checkGuidForApi($request->input('id'));
        if ($relData) {
            return redirect('/getnzb?r='.$request->input('api_token').'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : ''));
        }

        Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
    }

    public function details(Request $request): JsonResponse
    {
        if ($request->missing('id')) {
            Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
        }

        UserRequest::addApiRequest($request->input('api_token'), $request->getRequestUri());
        $userData = User::query()->where('api_token', $request->input('api_token'))->first();
        event(new UserAccessedApi($userData));
        $relData = Release::getByGuid($request->input('id'));

        $relData = fractal($relData, new DetailsTransformer($userData));

        return response()->json($relData);
    }
}
