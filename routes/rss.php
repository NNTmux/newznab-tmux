<?php


/*
|--------------------------------------------------------------------------
| RSS Routes
|--------------------------------------------------------------------------
|
| RSS feed routes
|
*/

Route::group(['middleware' => ['auth:api']], function () {
    Route::get('mymovies', 'RssController@myMoviesRss');
    Route::post('mymovies', 'RssController@myMoviesRss');
    Route::get('myshows', 'RssController@myShowsRss');
    Route::post('myshows', 'RssController@myShowsRss');
    Route::get('full-feed', 'RssController@feedRss');
    Route::post('full-feed', 'RssController@feedRss');
    Route::get('cart', 'RssController@cartRss');
    Route::post('cart', 'RssController@cartRss');
    Route::get('category', 'RssController@categoryRss');
    Route::post('category', 'RssController@categoryRss');
});

Route::get('help', 'RssController@showRssDesc');
Route::post('help', 'RssController@showRssDesc');
