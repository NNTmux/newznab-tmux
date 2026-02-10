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

Route::prefix('v1')->group(function () {
    Route::match(['post', 'get'], 'api', [ApiController::class, 'api']);
});

Route::prefix('v2')->group(function () {
    Route::get('capabilities', [ApiV2Controller::class, 'capabilities']);
});

Route::prefix('v2')->middleware(['auth:api', 'throttle:rate_limit,1'])->group(function () {
    Route::get('movies', [ApiV2Controller::class, 'movie']);
    Route::get('search', [ApiV2Controller::class, 'apiSearch']);
    Route::get('tv', [ApiV2Controller::class, 'tv']);
    Route::get('getnzb', [ApiV2Controller::class, 'getNzb']);
    Route::get('details', [ApiV2Controller::class, 'details']);
});

Route::prefix('inform')->middleware('auth:api')->group(function () {
    Route::get('release', [ApiInformController::class, 'release']);
});

// Mediainfo endpoint (no auth required for internal use)
Route::get('release/{id}/mediainfo', function ($id) {
    $releaseExtra = app(\App\Services\ReleaseExtraService::class);

    $video = $releaseExtra->getVideo($id);
    $audio = $releaseExtra->getAudio($id);
    $subs = $releaseExtra->getSubs($id);

    return response()->json([
        'video' => $video ?: null,
        'audio' => $audio ?: null,
        'subs' => $subs ? $subs->subs : null, // @phpstan-ignore property.notFound
    ]);
});

Route::fallback(function () {
    return response()->json(['message' => 'Not Found!'], 404);
});
