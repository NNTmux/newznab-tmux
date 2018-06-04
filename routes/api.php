<?php


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {
    Route::namespace('Api')->group(function () {
        Route::get('api', 'ApiController@api');
        Route::post('api', 'ApiController@api');
    });
});

Route::prefix('v2')->group(function () {
    Route::namespace('Api')->group(function () {
        Route::get('capabilities', 'ApiV2Controller@capabilities');
        Route::post('capabilities', 'ApiV2Controller@capabilities');
    });
});

Route::prefix('v2')->middleware('auth:api')->group(function () {
    Route::namespace('Api')->group(function () {
        Route::get('movies', 'ApiV2Controller@movie');
        Route::post('movies', 'ApiV2Controller@movie');
        Route::get('search', 'ApiV2Controller@search');
        Route::post('search', 'ApiV2Controller@search');
        Route::get('tv', 'ApiV2Controller@tv');
        Route::post('tv', 'ApiV2Controller@tv');
        Route::get('getnzb', 'ApiV2Controller@getNzb');
        Route::post('getnzb', 'ApiV2Controller@getNzb');
        Route::get('details', 'ApiV2Controller@details');
        Route::post('details', 'ApiV2Controller@details');
    });
});
