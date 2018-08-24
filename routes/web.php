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

Route::get('/', 'ContentController@show')->middleware('fw-block-blacklisted');

Auth::routes();

Route::get('register', 'Auth\RegisterController@showRegistrationForm')->middleware('fw-block-blacklisted');
Route::post('register', 'Auth\RegisterController@register')->middleware('fw-block-blacklisted');

Route::get('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm')->middleware('fw-block-blacklisted');
Route::post('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm')->middleware('fw-block-blacklisted');

Route::get('terms-and-conditions', 'TermsController@terms');

Route::get('login', 'Auth\LoginController@showLoginForm');
Route::post('login', 'Auth\LoginController@login')->name('login');

Route::group(['middleware' => ['isVerified', 'fw-block-blacklisted']], function () {
    Route::get('resetpassword', 'Auth\ResetPasswordController@reset');
    Route::post('resetpassword', 'Auth\ResetPasswordController@reset');

    Route::get('profile', 'ProfileController@show');

    Route::group(['prefix' => 'browse'], function () {
        Route::get('group', 'BrowseController@group');
        Route::get('all', 'BrowseController@index');
        Route::get('{parentCategory}/{id?}', 'BrowseController@show')->middleware('clearance');
    });

    Route::prefix('cart')->group(function () {
        Route::get('index', 'CartController@index');
        Route::post('index', 'CartController@index');
        Route::get('add', 'CartController@store');
        Route::post('add', 'CartController@store');
        Route::get('delete/{id}', 'CartController@destroy');
        Route::post('delete/{id}', 'CartController@destroy');
    });

    Route::get('details/{guid}', 'DetailsController@show');
    Route::post('details/{guid}', 'DetailsController@show');

    Route::get('getnzb', 'GetNzbController@getNzb');
    Route::post('getnzb', 'GetNzbController@getNzb');

    Route::get('rss', 'RssController@rss');

    Route::post('rss', 'RssController@rss');

    Route::get('profile', 'ProfileController@show');

    Route::get('apihelp', 'ApiHelpController@index');
    Route::get('apiv2help', 'ApiHelpController@apiv2');

    Route::get('browsegroup', 'BrowseGroupController@show');

    Route::get('content', 'ContentController@show');

    Route::post('content', 'ContentController@show');

    Route::get('failed', 'FailedReleasesController@show');

    Route::post('failed', 'FailedReleasesController@show');

    Route::group(['middleware' => 'clearance'], function () {
        Route::get('Games', 'GamesController@show');
        Route::post('Games', 'GamesController@show');

        Route::get('Movies/{id?}', 'MovieController@showMovies');

        Route::get('movie', 'MovieController@showMovie');

        Route::get('movietrailers', 'MovieController@showTrailer');

        Route::post('Movies/{id?}', 'MovieController@showMovies');

        Route::post('movie', 'MovieController@showMovie');

        Route::post('movietrailers', 'MovieController@showTrailer');

        Route::get('Audio/{id?}', 'MusicController@show');

        Route::post('Audio/{id?}', 'MusicController@show');

        Route::get('Console/{id?}', 'ConsoleController@show');

        Route::post('Console/{id?}', 'ConsoleController@show');

        Route::get('XXX/{id?}', 'AdultController@show');

        Route::post('XXX/{id?}', 'AdultController@show');

        Route::get('anime', 'AnimeController@showAnime');
        Route::post('anime', 'AnimeController@showAnime');

        Route::get('animelist', 'AnimeController@showList');
        Route::post('animelist', 'AnimeController@showList');

        Route::get('Books/{id?}', 'BooksController@index');
        Route::post('Books/{id?}', 'BooksController@index');
    });

    Route::get('nfo/{id?}', 'NfoController@showNfo');

    Route::post('nfo/{id?}', 'NfoController@showNfo');

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

    Route::get('myshows/browse', 'MyShowsController@browse');

    Route::post('myshows/browse', 'MyShowsController@browse');

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

    Route::get('series/{id?}', 'SeriesController@index');

    Route::post('series/{id?}', 'SeriesController@index');

    Route::get('nzbvortex', 'QueueController@nzbvortex');

    Route::post('nzbvortex', 'QueueController@nzbvortex');
});

Route::get('logout', 'Auth\LoginController@logout')->name('logout');

Route::get('forum-delete/{id}', 'ForumController@destroy')->middleware('role:Admin');

Route::post('forum-delete/{id}', 'ForumController@destroy')->middleware('role:Admin');

Route::group(['middleware' => ['role:Admin'], 'prefix' => 'admin', 'namespace' => 'Admin'], function () {
    Route::get('index', 'AdminPageController@index');
    Route::get('anidb-delete/{id}', 'AnidbController@destroy');
    Route::post('anidb-delete/{id}', 'AnidbController@destroy');
    Route::get('anidb-edit/{id}', 'AnidbController@edit');
    Route::post('anidb-edit/{id}', 'AnidbController@edit');
    Route::get('anidb-list', 'AnidbController@index');
    Route::post('anidb-list', 'AnidbController@index');
    Route::get('binaryblacklist-list', 'BlacklistController@index');
    Route::post('binaryblacklist-list', 'BlacklistController@index');
    Route::get('binaryblacklist-edit', 'BlacklistController@edit');
    Route::post('binaryblacklist-edit', 'BlacklistController@edit');
    Route::get('book-list', 'BookController@index');
    Route::post('book-list', 'BookController@index');
    Route::get('book-edit', 'BookController@edit');
    Route::post('book-edit', 'BookController@edit');
    Route::get('category-list', 'CategoryController@index');
    Route::post('category-list', 'CategoryController@index');
    Route::get('category-edit', 'CategoryController@edit');
    Route::post('category-edit', 'CategoryController@edit');
    Route::get('user-list', 'UserController@index');
    Route::post('user-list', 'UserController@index');
    Route::get('user-edit', 'UserController@edit');
    Route::post('user-edit', 'UserController@edit');
    Route::get('user-delete', 'UserController@destroy');
    Route::post('user-delete', 'UserController@destroy');
    Route::get('site-edit', 'SiteController@edit');
    Route::post('site-edit', 'SiteController@edit');
    Route::get('site-stats', 'SiteController@stats');
    Route::post('site-stats', 'SiteController@stats');
    Route::get('role-list', 'RoleController@index');
    Route::post('role-list', 'RoleController@index');
    Route::get('role-edit', 'RoleController@edit');
    Route::post('role-edit', 'RoleController@edit');
    Route::get('role-delete', 'RoleController@destroy');
    Route::post('role-delete', 'RoleController@destroy');
    Route::get('content-list', 'ContentController@index');
    Route::post('content-list', 'ContentController@index');
    Route::get('content-add', 'ContentController@create');
    Route::post('content-add', 'ContentController@create');
    Route::get('content-delete', 'ContentController@destroy');
    Route::post('content-delete', 'ContentController@destroy');
    Route::get('category_regexes-list', 'CategoryRegexesController@index');
    Route::post('category_regexes-list', 'CategoryRegexesController@index');
    Route::get('category_regexes-edit', 'CategoryRegexesController@edit');
    Route::post('category_regexes-edit', 'CategoryRegexesController@edit');
    Route::get('collection_regexes-list', 'CollectionRegexesController@index');
    Route::post('collection_regexes-list', 'CollectionRegexesController@index');
    Route::get('collection_regexes-edit', 'CollectionRegexesController@edit');
    Route::post('collection_regexes-edit', 'CollectionRegexesController@edit');
    Route::get('collection_regexes-test', 'CollectionRegexesController@testRegex');
    Route::post('collection_regexes-test', 'CollectionRegexesController@testRegex');
    Route::get('release_naming_regexes-list', 'ReleaseNamingRegexesController@index');
    Route::post('release_naming_regexes-list', 'ReleaseNamingRegexesController@index');
    Route::get('release_naming_regexes-edit', 'ReleaseNamingRegexesController@edit');
    Route::post('release_naming_regexes-edit', 'ReleaseNamingRegexesController@edit');
    Route::get('release_naming_regexes-test', 'ReleaseNamingRegexesController@testRegex');
    Route::post('release_naming_regexes-test', 'ReleaseNamingRegexesController@testRegex');
    Route::get('ajax', 'AjaxController@ajaxAction');
    Route::post('ajax', 'AjaxController@ajaxAction');
    Route::get('tmux-edit', 'TmuxController@edit');
    Route::post('tmux-edit', 'TmuxController@edit');
    Route::get('posters-list', 'MgrPosterController@index');
    Route::post('posters-list', 'MgrPosterController@index');
    Route::get('posters-edit/{id?}', 'MgrPosterController@edit');
    Route::post('posters-edit{id?}', 'MgrPosterController@edit');
    Route::get('poster-delete/{id}', 'MgrPosterController@destroy');
    Route::post('poster-delete/{id}', 'MgrPosterController@destroy');
    Route::get('release-list', 'ReleasesController@index');
    Route::post('release-list', 'ReleasesController@index');
    Route::get('release-delete/{id}', 'ReleasesController@destroy');
    Route::post('release-delete/{id}', 'ReleasesController@destroy');
    Route::get('show-list', 'ShowsController@index');
    Route::post('show-list', 'ShowsController@index');
    Route::get('show-edit', 'ShowsController@edit');
    Route::post('show-edit', 'ShowsController@edit');
    Route::get('show-remove', 'ShowsController@destroy');
    Route::post('show-remove', 'ShowsController@destroy');
    Route::get('comments-list', 'CommentsController@index');
    Route::post('comments-list', 'CommentsController@index');
    Route::get('comments-delete/{id}', 'CommentsController@destroy');
    Route::post('comments-delete/{id}', 'CommentsController@destroy');
    Route::get('console-list', 'ConsoleController@index');
    Route::post('console-list', 'ConsoleController@index');
    Route::get('console-edit', 'ConsoleController@edit');
    Route::post('console-edit', 'ConsoleController@edit');
    Route::get('failrel-list', 'FailedReleasesController@index');
    Route::get('game-list', 'GameController@index');
    Route::post('game-list', 'GameController@index');
    Route::get('game-edit', 'GameController@edit');
    Route::post('game-edit', 'GameController@edit');
    Route::get('menu-list', 'MenuController@index');
    Route::post('menu-list', 'MenuController@index');
    Route::get('menu-edit', 'MenuController@edit');
    Route::post('menu-edit', 'MenuController@edit');
    Route::get('menu-delete/{id}', 'MenuController@destroy');
    Route::post('menu-delete/{id}', 'MenuController@destroy');
    Route::get('movie-list', 'MovieController@index');
    Route::post('movie-list', 'MovieController@index');
    Route::get('movie-edit', 'MovieController@edit');
    Route::post('movie-edit', 'MovieController@edit');
    Route::get('movie-add', 'MovieController@create');
    Route::post('movie-add', 'MovieController@create');
    Route::get('music-list', 'MusicController@index');
    Route::post('music-list', 'MusicController@index');
    Route::get('music-edit', 'MusicController@edit');
    Route::post('music-edit', 'MusicController@edit');
    Route::get('nzb-import', 'NzbController@import');
    Route::post('nzb-import', 'NzbController@import');
    Route::get('nzb-export', 'NzbController@export');
    Route::post('nzb-export', 'NzbController@export');
    Route::get('predb', 'PredbController@index');
    Route::post('predb', 'PredbController@index');
    Route::get('sharing', 'SharingController@index');
    Route::post('sharing', 'SharingController@index');
    Route::get('group-list', 'GroupController@index');
    Route::post('group-list', 'GroupController@index');
    Route::get('group-edit', 'GroupController@edit');
    Route::post('group-edit', 'GroupController@edit');
    Route::get('group-bulk', 'GroupController@createBulk');
    Route::post('group-bulk', 'GroupController@createBulk');
    Route::get('group-list-active', 'GroupController@active');
    Route::post('group-list-active', 'GroupController@active');
    Route::get('group-list-inactive', 'GroupController@inactive');
    Route::post('group-list-inactive', 'GroupController@inactive');
});

Route::group(['middleware' => ['role:Admin|Moderator|permission:edit release'], 'prefix' => 'admin', 'namespace' => 'Admin'], function () {
    Route::get('release-edit', 'ReleasesController@edit');
    Route::post('release-edit', 'ReleasesController@edit');
});
