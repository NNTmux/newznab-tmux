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

Auth::routes();

Route::get('/', 'ContentController@show');

Route::get('register', 'Auth\RegisterController@showregistrationForm');
Route::post('register', 'Auth\RegisterController@register')->name('register');

Route::get('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('forgottenpassword');
Route::post('forgottenpassword', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('forgottenpassword');

Route::get('terms-and-conditions', 'TermsController@terms');

Route::get('login', 'Auth\LoginController@showLoginForm');
Route::post('login', 'Auth\LoginController@login')->name('login');
Route::get('logout', 'Auth\LoginController@logout')->name('logout');

Route::group(['middleware' => ['isVerified']], function () {
    Route::get('resetpassword', 'Auth\ResetPasswordController@reset');
    Route::post('resetpassword', 'Auth\ResetPasswordController@reset');

    Route::get('profile', 'ProfileController@show');

    Route::group(['prefix' => 'browse'], function () {
        Route::get('tags', 'BrowseController@tags');
        Route::get('group', 'BrowseController@group');
        Route::get('All', 'BrowseController@index');
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

    Route::get('getnzb', 'GetNzbController@getNzb')->name('getnzb');
    Route::post('getnzb', 'GetNzbController@getNzb')->name('getnzb');

    Route::get('rsshelp', 'RssController@showRssDesc')->name('rsshelp');
    Route::post('rsshelp', 'RssController@showRssDesc')->name('rsshelp');

    Route::get('profile', 'ProfileController@show')->name('profile');

    Route::get('apihelp', 'ApiHelpController@index')->name('apihelp');
    Route::get('apiv2help', 'ApiHelpController@apiv2')->name('apiv2help');

    Route::get('browsegroup', 'BrowseGroupController@show')->name('browsegroup');

    Route::get('content', 'ContentController@show')->name('content');

    Route::post('content', 'ContentController@show')->name('content');

    Route::get('failed', 'FailedReleasesController@failed')->name('failed');

    Route::post('failed', 'FailedReleasesController@failed')->name('failed');

    Route::group(['middleware' => 'clearance'], function () {
        Route::get('Games', 'GamesController@show')->name('Games');
        Route::post('Games', 'GamesController@show')->name('Games');

        Route::get('Movies/{id?}', 'MovieController@showMovies')->name('Movies');

        Route::get('movie', 'MovieController@showMovie')->name('movie');

        Route::get('movietrailers', 'MovieController@showTrailer')->name('movietrailers');

        Route::post('Movies/{id?}', 'MovieController@showMovies')->name('Movies');

        Route::post('movie', 'MovieController@showMovie')->name('movie');

        Route::post('movietrailers', 'MovieController@showTrailer')->name('movietrailers');

        Route::get('Audio/{id?}', 'MusicController@show')->name('Audio');

        Route::post('Audio/{id?}', 'MusicController@show')->name('Audio');

        Route::get('Console/{id?}', 'ConsoleController@show')->name('Console');

        Route::post('Console/{id?}', 'ConsoleController@show')->name('Console');

        Route::get('XXX/{id?}', 'AdultController@show')->name('XXX');

        Route::post('XXX/{id?}', 'AdultController@show')->name('XXX');

        Route::get('anime', 'AnimeController@showAnime')->name('anime');
        Route::post('anime', 'AnimeController@showAnime')->name('anime');

        Route::get('animelist', 'AnimeController@showList')->name('animelist');
        Route::post('animelist', 'AnimeController@showList')->name('animelist');

        Route::get('Books/{id?}', 'BooksController@index')->name('Books');
        Route::post('Books/{id?}', 'BooksController@index')->name('Books');
    });

    Route::get('nfo/{id?}', 'NfoController@showNfo')->name('nfo');

    Route::post('nfo/{id?}', 'NfoController@showNfo')->name('nfo');

    Route::get('contact-us', 'ContactUsController@showContactForm')->name('contact-us');
    Route::post('contact-us', 'ContactUsController@contact')->name('contact-us');

    Route::get('forum', 'ForumController@forum')->name('forum');

    Route::post('forum', 'ForumController@forum')->name('forum');

    Route::get('forumpost/{id}', 'ForumController@getPosts')->name('forumpost');

    Route::post('forumpost/{id}', 'ForumController@getPosts')->name('forumpost');

    Route::get('topic_delete', 'ForumController@deleteTopic')->name('topic_delete');

    Route::post('topic_delete', 'ForumController@deleteTopic')->name('topic_delete');

    Route::get('post_edit', 'ForumController@edit')->name('post_edit');

    Route::post('post_edit', 'ForumController@edit')->name('post_edit');

    Route::get('profileedit', 'ProfileController@edit')->name('profileedit');

    Route::post('profileedit', 'ProfileController@edit')->name('profileedit');

    Route::get('profile_delete', 'ProfileController@destroy')->name('profile_delete');

    Route::post('profile_delete', 'ProfileController@destroy')->name('profile_delete');

    Route::get('search', 'SearchController@search')->name('search');

    Route::post('search', 'SearchController@search')->name('search');

    Route::get('mymovies', 'MyMoviesController@show')->name('mymovies');

    Route::post('mymovies', 'MyMoviesController@show')->name('mymovies');

    Route::get('myshows', 'MyShowsController@show')->name('myshows');

    Route::post('myshows', 'MyShowsController@show')->name('myshows');

    Route::get('myshows/browse', 'MyShowsController@browse');

    Route::post('myshows/browse', 'MyShowsController@browse');

    Route::get('filelist/{guid}', 'FileListController@show');

    Route::post('filelist/{guid}', 'FileListController@show');

    Route::get('btc_payment', 'BtcPaymentController@show')->name('btc_payment');

    Route::post('btc_payment', 'BtcPaymentController@show')->name('btc_payment');

    Route::get('btc_payment_callback', 'BtcPaymentController@callback')->name('btc_payment_callback');

    Route::post('btc_payment_callback', 'BtcPaymentController@callback')->name('btc_payment_callback');

    Route::get('pay_by_paypal', 'BtcPaymentController@showPaypal')->name('pay_by_paypal');

    Route::post('pay_by_paypal', 'BtcPaymentController@showpaypal')->name('pay_by_paypal');

    Route::get('paypal', 'BtcPaymentController@paypal')->name('paypal');

    Route::post('paypal', 'BtcPaymentController@paypal')->name('paypal');

    Route::get('thankyou', 'BtcPaymentController@paypalCallback')->name('thankyou');

    Route::post('thankyou', 'BtcPaymentController@paypalCallback')->name('thankyou');

    Route::get('payment_failed', 'BtcPaymentController@paypalFailed')->name('payment_failed');

    Route::post('payment_failed', 'BtcPaymentController@paypalFailed')->name('payment_failed');

    Route::get('queue', 'QueueController@index')->name('queue');

    Route::post('queue', 'QueueController@index')->name('queue');

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

    Route::get('series/{id?}', 'SeriesController@index')->name('series');

    Route::post('series/{id?}', 'SeriesController@index')->name('series');

    Route::get('nzbvortex', 'QueueController@nzbvortex');

    Route::post('nzbvortex', 'QueueController@nzbvortex');

    Route::get('ajax_profile', 'AjaxController@profile');

    Route::post('ajax_profile', 'AjaxController@profile');
});

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
    Route::get('verify', 'UserController@verify');
    Route::post('verify', 'UserController@verify');
    Route::get('resendverification', 'UserController@resendVerification');
    Route::post('resendverification', 'UserController@resendVerification');
    Route::get('site-edit', 'SiteController@edit');
    Route::post('site-edit', 'SiteController@edit');
    Route::get('site-stats', 'SiteController@stats');
    Route::post('site-stats', 'SiteController@stats');
    Route::get('role-list', 'RoleController@index');
    Route::post('role-list', 'RoleController@index');
    Route::get('role-add', 'RoleController@create');
    Route::post('role-add', 'RoleController@create');
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

Route::group(['middleware' => ['role_or_permission:Admin|Moderator|edit release'], 'prefix' => 'admin', 'namespace' => 'Admin'], function () {
    Route::get('release-edit', 'ReleasesController@edit');
    Route::post('release-edit', 'ReleasesController@edit');
});
