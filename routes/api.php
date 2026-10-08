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
use App\Http\Controllers\Api\ApiQueryOptionsController;
use App\Http\Controllers\Api\ApiV2Controller;
use App\Services\ReleaseExtraService;

// HTTP QUERY (RFC 10008) is accepted on read-only search endpoints only; see ValidateHttpQueryInput.
Route::prefix('v1')->group(function () {
    Route::match(['post', 'get', 'query'], 'api', [ApiController::class, 'api'])->middleware('acceptQuery:v1');
    Route::options('api', ApiQueryOptionsController::class);
});

Route::prefix('v2')->group(function () {
    Route::get('capabilities', [ApiV2Controller::class, 'capabilities']);
    Route::post('nzbadd', [ApiV2Controller::class, 'nzbAdd']);
});

// acceptQuery runs before apiRateLimit so malformed QUERY bodies never consume quota.
Route::prefix('v2')->middleware(['acceptQuery:v2', 'apiRateLimit'])->group(function () {
    Route::match(['get', 'query'], 'movies', [ApiV2Controller::class, 'movie']);
    Route::match(['get', 'query'], 'audio', [ApiV2Controller::class, 'audio']);
    Route::match(['get', 'query'], 'books', [ApiV2Controller::class, 'books']);
    Route::match(['get', 'query'], 'anime', [ApiV2Controller::class, 'anime']);
    Route::match(['get', 'query'], 'search', [ApiV2Controller::class, 'apiSearch']);
    Route::match(['get', 'query'], 'tv', [ApiV2Controller::class, 'tv']);
    Route::get('getnzb', [ApiV2Controller::class, 'getNzb']);
    Route::match(['get', 'query'], 'details', [ApiV2Controller::class, 'details']);
});

Route::prefix('v2')->group(function () {
    foreach (['movies', 'audio', 'books', 'anime', 'search', 'tv', 'details'] as $uri) {
        Route::options($uri, ApiQueryOptionsController::class);
    }
});

Route::prefix('inform')->group(function () {
    Route::get('release', [ApiInformController::class, 'release']);
});

// Mediainfo endpoint (no auth required for internal use)
Route::get('release/{id}/mediainfo', function ($id) {
    $releaseExtra = app(ReleaseExtraService::class);

    $video = $releaseExtra->getVideo($id);
    $audio = $releaseExtra->getAudio($id);
    $subs = $releaseExtra->getSubs($id);

    return response()->json([
        'video' => $video ?: null,
        'audio' => $audio ?: null,
        'subs' => $subs ? $subs->subs : null, // @phpstan-ignore property.notFound
    ]);
})->middleware('throttle:60,1');

Route::fallback(function () {
    return response()->json(['message' => 'Not Found!'], 404);
});
