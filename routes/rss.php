<?php

/*
|--------------------------------------------------------------------------
| RSS Routes
|--------------------------------------------------------------------------
|
| RSS feed routes
|
*/

Route::group(['guard' => 'rss', 'middleware' => ['auth:api']], function () {
    Route::get('mymovies', 'RssController@myMoviesRss');
    Route::post('mymovies', 'RssController@myMoviesRss');
    Route::get('myshows', 'RssController@myShowsRss');
    Route::post('myshows', 'RssController@myShowsRss');
    Route::get('full-feed', 'RssController@fullFeedRss');
    Route::post('full-feed', 'RssController@fullFeedRss');
    Route::get('cart', 'RssController@cartRss');
    Route::post('cart', 'RssController@cartRss');
    Route::get('category', 'RssController@categoryFeedRss');
    Route::post('category', 'RssController@categoryFeedRss');
});
