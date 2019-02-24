<?php


/*
|--------------------------------------------------------------------------
| RSS Routes
|--------------------------------------------------------------------------
|
| RSS feed routes
|
*/

Route::get('rss', 'RssController@rss');
Route::post('rss', 'RssController@rss');