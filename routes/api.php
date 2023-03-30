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

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ApiInformController;
use App\Http\Controllers\Api\ApiV2Controller;

Route::prefix('v1')->namespace('Api')->group(function () {
    Route::get('api', [ApiController::class, 'api']);
    Route::post('api', [ApiController::class, 'api']);
});

Route::prefix('v2')->namespace('Api')->group(function () {
    Route::get('capabilities', [ApiV2Controller::class, 'capabilities']);
    Route::post('capabilities', [ApiV2Controller::class, 'capabilities']);
});

Route::prefix('v2')->namespace('Api')->middleware('auth:api', 'throttle:rate_limit,1')->group(function () {
    Route::get('movies', [ApiV2Controller::class, 'movie']);
    Route::post('movies', [ApiV2Controller::class, 'movie']);
    Route::get('search', [ApiV2Controller::class, 'apiSearch']);
    Route::post('search', [ApiV2Controller::class, 'apiSearch']);
    Route::get('tv', [ApiV2Controller::class, 'tv']);
    Route::post('tv', [ApiV2Controller::class, 'tv']);
    Route::get('getnzb', [ApiV2Controller::class, 'getNzb']);
    Route::post('getnzb', [ApiV2Controller::class, 'getNzb']);
    Route::get('details', [ApiV2Controller::class, 'details']);
    Route::post('details', [ApiV2Controller::class, 'details']);
});

Route::prefix('inform')->namespace('Api')->middleware('auth:api')->group(function () {
    Route::get('release', [ApiInformController::class, 'release']);
    Route::post('release', [ApiInformController::class, 'release']);
});

Route::fallback(function () {
    return response()->json(['message' => 'Not Found!'], 404);
});
