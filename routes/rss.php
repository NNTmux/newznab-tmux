<?php


/*
|--------------------------------------------------------------------------
| RSS Routes
|--------------------------------------------------------------------------
|
| RSS feed routes
|
*/

Route::get('mymovies', 'RssController@MyMoviesRss');
Route::post('mymovies', 'RssController@yMoviesRss');
Route::get('myshows', 'RssController@yShowsRss');
Route::post('myshows', 'RssController@myShowsRss');
Route::get('full-feed', 'RssController@feedRss');
Route::post('full-feed', 'RssController@feedRss');
Route::get('help', 'RssController@showRssDesc');
Route::post('help', 'RssController@showRssDesc');
Route::get('cart', 'RssController@cartRss');
Route::post('cart', 'RssController@cartRss');
Route::get('category', 'RssController@categoryRss');
Route::post('category', 'RssController@categoryRss');
