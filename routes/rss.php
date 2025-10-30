<?php

/*
|--------------------------------------------------------------------------
| RSS Routes
|--------------------------------------------------------------------------
|
| RSS feed routes
| Note: Authentication is handled manually in the controller via userCheck()
|       method which validates the api_token parameter.
|
*/

use App\Http\Controllers\RssController;

Route::middleware(['auth:api', 'auth:rss'])->group(function () {
    Route::get('mymovies', [RssController::class, 'myMoviesRss']);
    Route::post('mymovies', [RssController::class, 'myMoviesRss']);
    Route::get('myshows', [RssController::class, 'myShowsRss']);
    Route::post('myshows', [RssController::class, 'myShowsRss']);
    Route::get('full-feed', [RssController::class, 'fullFeedRss']);
    Route::post('full-feed', [RssController::class, 'fullFeedRss']);
    Route::get('cart', [RssController::class, 'cartRss']);
    Route::post('cart', [RssController::class, 'cartRss']);
    Route::get('category', [RssController::class, 'categoryFeedRss']);
    Route::post('category', [RssController::class, 'categoryFeedRss']);
    Route::get('trending-movies', [RssController::class, 'trendingMoviesRss']);
    Route::post('trending-movies', [RssController::class, 'trendingMoviesRss']);
    Route::get('trending-shows', [RssController::class, 'trendingShowsRss']);
    Route::post('trending-shows', [RssController::class, 'trendingShowsRss']);
});
