<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Release;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\http\API;
use Blacklight\Releases;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use App\Extensions\util\Versions;
use App\Http\Controllers\Controller;
use App\Transformers\ApiTransformer;
use Illuminate\Support\Facades\Auth;
use App\Transformers\DetailsTransformer;
use App\Transformers\CategoryTransformer;

class ApiV2Controller extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function capabilities(): \Illuminate\Http\JsonResponse
    {
        $serverroot = url('/');
        $category = Category::getForApi();

        $capabilities = [
            'server' => [
                'appversion' => (new Versions())->getGitTagInFile(),
                'version'    => (new Versions())->getGitTagInRepo(),
                'title'      => Settings::settingValue('site.main.title'),
                'strapline'  => Settings::settingValue('site.main.strapline'),
                'email'      => Settings::settingValue('site.main.email'),
                'url'        => $serverroot,
            ],
            'limits' => [
                'max'     => 100,
                'default' => 100,
            ],
            'registration' => [
                'available' => 'no',
                'open'      => (int) Settings::settingValue('..registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search'       => ['available' => 'yes', 'supportedParams' => 'id'],
                'tv-search'    => ['available' => 'yes', 'supportedParams' => 'id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'id, imdbid, tmdbid, traktid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => fractal($category, new CategoryTransformer()),
        ];

        return response()->json($capabilities);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function movie(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $api = new API();
        $releases = new Releases();
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $maxAge = $api->maxAge();
        $catExclusions = User::getCategoryExclusion($user->id);
        UserRequest::addApiRequest($user->id, $request->getRequestUri());

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

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $api = new API();
        $releases = new Releases();
        $offset = $api->offset();
        $catExclusions = User::getCategoryExclusion($user->id);
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $tags = $request->has('tags') && ! empty($request->input('tags')) ? explode(',', $request->input('tags')) : [];
        $maxAge = $api->maxAge();
        $groupName = $api->group();
        UserRequest::addApiRequest($user->id, $request->getRequestUri());
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

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function tv(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $api = new API();
        $releases = new Releases();
        $catExclusions = User::getCategoryExclusion($user->id);
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
        UserRequest::addApiRequest($user->id, $request->getRequestUri());

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

        $relData = $releases->apiTvSearch(
            $siteIdArr,
            $series,
            $episode,
            $airdate ?? '',
            $api->offset(),
            $api->limit(),
            $request->input('id') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize,
            $catExclusions,
            $tags
        );

        $response = [
            'Total' => $relData[0]->_totalrows ?? 0,
            'Results' => fractal($relData, new ApiTransformer($user)),
        ];

        return response()->json($response);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getNzb(Request $request)
    {
        $user = Auth::user();
        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        $relData = Release::checkGuidForApi($request->input('id'));
        if ($relData !== false) {
            return redirect('/getnzb?i='.$user->id.'&r='.$user->api_token.'&id='.$request->input('id').(($request->has('del') && $request->input('del') === '1') ? '&del=1' : ''));
        }

        Utility::showApiError(300, 'No such item (the guid you provided has no release in our database)');
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (! $request->has('id')) {
            Utility::showApiError(200, 'Missing parameter (guid is required for single release details)');
        }

        UserRequest::addApiRequest($user->id, $request->getRequestUri());
        $relData = Release::getByGuid($request->input('id'));

        $relData = fractal($relData, new DetailsTransformer($user));

        return response()->json($relData);
    }
}
