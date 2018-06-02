<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Settings;
use Blacklight\http\API;
use Blacklight\Releases;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use App\Extensions\util\Versions;
use App\Http\Controllers\Controller;
use App\Transformers\MoviesTransformer;
use App\Transformers\CategoryTransformer;

class ApiV2Controller extends Controller
{
    /**
     * @throws \Exception
     */
    public function capabilities()
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
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'id, imdbid'],
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
    public function movie(Request $request)
    {
        $api = new API();
        $releases = new Releases();
        $minSize = $request->has('minsize') && $request->input('minsize') > 0 ? $request->input('minsize') : 0;
        $maxAge = $api->maxAge();
        UserRequest::addApiRequest(\Auth::id(), $request->getRequestUri());

        $imdbId = $request->input('imdbid') ?? -1;

        $relData = $releases->moviesSearch(
            $imdbId,
            $api->offset(),
            $api->limit(),
            $request->input('id') ?? '',
            $api->categoryID(),
            $maxAge,
            $minSize
        );

        $relData = fractal($relData, new MoviesTransformer());

        return response()->json($relData);
    }
}
