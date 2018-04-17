<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'BasePageController@basePage');

Auth::routes();

Route::get('login', 'Auth\LoginController@showLoginForm');
Route::post('login', 'Auth\LoginController@login')->name('login');

Route::get('register', 'Auth\RegisterController@showRegistrationForm');
Route::post('register', 'Auth\RegisterController@register');

Route::get('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');
Route::post('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm');

Route::get('resetpassword', 'Auth\ResetPasswordController@reset');
Route::post('resetpassword', 'Auth\ResetPasswordController@reset');

Route::get('profile', 'ProfileController@show');

Route::get('browse', 'BrowseController@group');

Route::prefix('browse')->group(function () {
    Route::get('group', 'BrowseController@group');
    Route::get('all', 'BrowseController@index');
    Route::get('{parentCategory}/{id?}', 'BrowseController@show');
});

Route::get('anime/{id?}', 'AnimeController@index');

Route::get('books/{id?}', 'BooksController@index');

Route::prefix('cart')->group(function () {
    Route::get('index', 'CartController@index');
    Route::get('add/{id}', 'CartController@store');
    Route::post('add/{id}', 'CartController@store');
    Route::get('delete/{id}', 'CartController@destroy');
    Route::post('delete/{id}', 'CartController@destroy');
});

Route::get('api', 'Api\ApiController@api')->middleware('api');
Route::post('api', 'Api\ApiController@api')->middleware('api');

Route::get('details/{guid}', 'DetailsController@show');
Route::post('details/{guid}', 'DetailsController@show');

Route::get('getnzb', 'GetNzbController@getNzb');
Route::post('getnzb', 'GetNzbController@getNzb');

Route::get('rss', 'RssController@rss');

Route::post('rss', 'RssController@rss');

Route::get('profile', 'ProfileController@show');

Route::get('apihelp', 'ApiHelpController@index');

Route::get('logout', 'Auth\LoginController@logout')->name('logout');

Route::get('console', 'ConsoleController@show');

Route::get('browsegroup', 'BrowseGroupController@show');

Route::get('content', 'ContentController@show');

Route::post('content', 'ContentController@show');

Route::get('failed', 'FailedReleasesController@show');

Route::post('failed', 'FailedReleasesController@show');

Route::get('games', 'GamesController@show');

Route::get('movies', 'MovieController@showMovies');

Route::get('movie', 'MovieController@showMovie');

Route::get('movietrailers', 'MovieController@showTrailer');

Route::post('movies', 'MovieController@showMovies');

Route::post('movie', 'MovieController@showMovie');

Route::post('movietrailers', 'MovieController@showTrailer');

Route::get('music', 'MusicController@show');

Route::post('music', 'MusicController@show');

Route::get('nfo', 'NfoController@showNfo');

Route::post('nfo', 'NfoController@showNfo');

Route::get('xxx', 'AdultController@show');

Route::post('xxx', 'AdultController@show');

Route::get('contact-us', 'ContactUsController@showContactForm');
Route::post('contact-us', 'ContactUsController@contact');

Route::get('forum', 'ForumController@forum');

Route::post('forum', 'ForumController@forum');

Route::get('forumpost/{id}', 'ForumController@getPosts');

Route::post('forumpost/{id}', 'ForumController@getPosts');

Route::get('topic_delete', 'ForumController@deleteTopic');

Route::post('topic_delete', 'ForumController@deleteTopic');

Route::get('post_edit', 'ForumController@edit');

Route::post('post_edit', 'ForumController@edit');

Route::get('profileedit', 'ProfileController@edit');

Route::post('profileedit', 'ProfileController@edit');

Route::get('/profile_delete', 'ProfileController@destroy');

Route::post('/profile_delete', 'ProfileController@destroy');

Route::get('search', 'SearchController@search');

Route::post('search', 'SearchController@search');

Route::get('mymovies', 'MyMoviesController@show');

Route::post('mymovies', 'MyMoviesController@show');

Route::get('myshows', 'MyShowsController@show');

Route::post('myshows', 'MyShowsController@show');

Route::get('filelist/{guid}', 'FileListController@show');

Route::post('filelist/{guid}', 'FileListController@show');

Route::get('btc_payment', 'BtcPaymentController@show');

Route::post('btc_payment', 'BtcPaymentController@show');

Route::get('btc_payment_callback', 'BtcPaymentController@callback');

Route::post('btc_payment_callback', 'BtcPaymentController@callback');

Route::get('queue', 'QueueController@index');

Route::post('queue', 'QueueController@index');

Route::get('nzbgetqueuedata', 'QueueController@nzbget');

Route::post('nzbgetqueuedata', 'QueueController@nzbget');

Route::get('sabqueuedata', 'QueueController@sabnzbd');

Route::post('sabqueuedata', 'QueueController@sabnzbd');

Route::get('sendtosab', 'SendReleaseController@sabNzbd');

Route::post('sendtosab', 'SendReleaseController@sabNzbd');

Route::get('sendtonzbget', 'SendReleaseController@nzbGet');

Route::post('sendtonzbget', 'SendReleaseController@nzbGet');

Route::get('sendtoqueue', 'SendReleaseController@queue');

Route::post('sendtoqueue', 'SendReleaseController@queue');

Route::get('sendtocouch', 'SendReleaseController@couchPotato');

Route::post('sendtocouch', 'SendReleaseController@couchPotato');

Route::get('series', 'SeriesController@index');

Route::post('series', 'SeriesController@index');

Route::get('terms-and-conditions', 'TermsController@index');

Route::get('nzbvortex', 'QueueController@nzbvortex');

Route::post('nzbvortex', 'QueueController@nzbvortex');

Route::prefix('admin')->group(function () {
    Route::get('index', 'Admin\AdminPageController@index');
    Route::get('anidb-delete/{id}', 'Admin\AnidbController@destroy');
    Route::post('anidb-delete/{id}', 'Admin\AnidbController@destroy');
    Route::get('anidb-edit/{id}', 'Admin\AnidbController@edit');
    Route::post('anidb-edit/{id}', 'Admin\AnidbController@edit');
    Route::get('anidb-list', 'Admin\AnidbController@index');
    Route::post('anidb-list', 'Admin\AnidbController@index');
    Route::get('binaryblacklist-list', 'Admin\BlacklistController@index');
    Route::post('binaryblacklist-list', 'Admin\BlacklistController@index');
    Route::get('binaryblacklist-edit', 'Admin\BlacklistController@edit');
    Route::post('binaryblacklist-edit', 'Admin\BlacklistController@edit');
    Route::get('book-list', 'Admin\BookController@index');
    Route::post('book-list', 'Admin\BookController@index');
    Route::get('book-edit', 'Admin\BookController@edit');
    Route::post('book-edit', 'Admin\BookController@edit');
    Route::get('category-list', 'Admin\CategoryController@index');
    Route::post('category-list', 'Admin\CategoryController@index');
    Route::get('category-edit', 'Admin\CategoryController@edit');
    Route::post('category-edit', 'Admin\CategoryController@edit');
    Route::get('user-list', 'Admin\UserController@index');
    Route::post('user-list', 'Admin\UserController@index');
    Route::get('user-edit', 'Admin\UserController@edit');
    Route::post('user-edit', 'Admin\UserController@edit');
    Route::get('user-delete', 'Admin\UserController@destroy');
    Route::post('user-delete', 'Admin\UserController@destroy');
    Route::get('site-edit', 'Admin\SiteController@edit');
    Route::post('site-edit', 'Admin\SiteController@edit');
    Route::get('site-stats', 'Admin\SiteController@stats');
    Route::post('site-stats', 'Admin\SiteController@stats');
});
