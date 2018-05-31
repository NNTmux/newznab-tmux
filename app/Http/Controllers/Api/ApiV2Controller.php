<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Settings;
use App\Extensions\util\Versions;
use App\Http\Controllers\Controller;

class ApiV2Controller extends Controller
{
    /**
     * @throws \Exception
     */
    public function capabilities()
    {
        $serverroot = url('/');
        $category = Category::getForMenu();

        $capabilities = [
            'server' => [
                'appversion' => (new Versions())->getGitTagInFile(),
                'version'    => (new Versions())->getGitTagInRepo(),
                'title'      => Settings::settingValue('site.main.title'),
                'strapline'  => Settings::settingValue('site.main.strapline'),
                'email'      => Settings::settingValue('site.main.email'),
                'meta'       => Settings::settingValue('site.main.metakeywords'),
                'url'        => $serverroot,
                'image'      => $serverroot.'/assets/images/tmux_logo.png',
            ],
            'limits' => [
                'max'     => 100,
                'default' => 100,
            ],
            'registration' => [
                'available' => 'yes',
                'open'      => (int) Settings::settingValue('..registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search'       => ['available' => 'yes', 'supportedParams' => 'q'],
                'tv-search'    => ['available' => 'yes', 'supportedParams' => 'q,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'q,imdbid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => $category,
        ];

        return response()->json($capabilities);
    }
}
