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

use App\Http\Controllers\Admin\AdminAjaxController;
use App\Http\Controllers\Admin\AdminAnidbController;
use App\Http\Controllers\Admin\AdminBlacklistController;
use App\Http\Controllers\Admin\AdminBookController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCategoryRegexesController;
use App\Http\Controllers\Admin\AdminCollectionRegexesController;
use App\Http\Controllers\Admin\AdminCommentsController;
use App\Http\Controllers\Admin\AdminConsoleController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminFailedReleasesController;
use App\Http\Controllers\Admin\AdminGameController;
use App\Http\Controllers\Admin\AdminGroupController;
use App\Http\Controllers\Admin\AdminMovieController;
use App\Http\Controllers\Admin\AdminMusicController;
use App\Http\Controllers\Admin\AdminNzbController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminPredbController;
use App\Http\Controllers\Admin\AdminReleaseNamingRegexesController;
use App\Http\Controllers\Admin\AdminReleasesController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminShowsController;
use App\Http\Controllers\Admin\AdminSiteController;
use App\Http\Controllers\Admin\AdminTmuxController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AdultController;
use App\Http\Controllers\AjaxController;
use App\Http\Controllers\AnimeController;
use App\Http\Controllers\ApiHelpController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BooksController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\BrowseGroupController;
use App\Http\Controllers\BtcPaymentController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DetailsController;
use App\Http\Controllers\FailedReleasesController;
use App\Http\Controllers\FileListController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\GetNzbController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\MyMoviesController;
use App\Http\Controllers\MyShowsController;
use App\Http\Controllers\NfoController;
use App\Http\Controllers\PasswordSecurityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\TermsController;
use App\Models\User;

Auth::routes();

Route::get('/', [ContentController::class, 'show']);

Route::get('register', [RegisterController::class, 'showRegistrationForm']);
Route::post('register', [RegisterController::class, 'register'])->name('register');

Route::get('forgottenpassword', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('forgottenpassword');
Route::post('forgottenpassword', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('forgottenpassword');

Route::get('terms-and-conditions', [TermsController::class, 'terms']);

Route::get('login', [LoginController::class, 'showLoginForm']);
Route::post('login', [LoginController::class, 'login'])->name('login');
Route::get('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('isVerified')->group(function () {
    Route::get('resetpassword', [ResetPasswordController::class, 'reset']);
    Route::post('resetpassword', [ResetPasswordController::class, 'reset']);

    Route::get('profile', [ProfileController::class, 'show']);

    Route::prefix('browse')->group(function () {
        Route::get('tags', [BrowseController::class, 'tags']);
        Route::get('group', [BrowseController::class, 'group']);
        Route::get('All', [BrowseController::class, 'index']);
        Route::get('{parentCategory}/{id?}', [BrowseController::class, 'show'])->middleware('clearance');
    });

    Route::prefix('cart')->group(function () {
        Route::get('index', [CartController::class, 'index']);
        Route::post('index', [CartController::class, 'index']);
        Route::get('add', [CartController::class, 'store']);
        Route::post('add', [CartController::class, 'store']);
        Route::get('delete/{id}', [CartController::class, 'destroy']);
        Route::post('delete/{id}', [CartController::class, 'destroy']);
    });

    Route::get('details/{guid}', [DetailsController::class, 'show']);
    Route::post('details/{guid}', [DetailsController::class, 'show']);

    Route::get('getnzb', [GetNzbController::class, 'getNzb'])->name('getnzb');
    Route::post('getnzb', [GetNzbController::class, 'getNzb'])->name('getnzb');

    Route::get('rsshelp', [RssController::class, 'showRssDesc'])->name('rsshelp');
    Route::post('rsshelp', [RssController::class, 'showRssDesc'])->name('rsshelp');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile');

    Route::get('apihelp', [ApiHelpController::class, 'index'])->name('apihelp');
    Route::get('apiv2help', [ApiHelpController::class, 'apiv2'])->name('apiv2help');

    Route::get('browsegroup', [BrowseGroupController::class, 'show'])->name('browsegroup');

    Route::get('content', [ContentController::class, 'show'])->name('content');

    Route::post('content', [ContentController::class, 'show'])->name('content');

    Route::get('failed', [FailedReleasesController::class, 'failed'])->name('failed');

    Route::post('failed', [FailedReleasesController::class, 'failed'])->name('failed');

    Route::middleware('clearance')->group(function () {
        Route::get('Games', [GamesController::class, 'show'])->name('Games');
        Route::post('Games', [GamesController::class, 'show'])->name('Games');

        Route::get('Movies/{id?}', [MovieController::class, 'showMovies'])->name('Movies');

        Route::get('movie', [MovieController::class, 'showMovies'])->name('movie');

        Route::get('movietrailers', [MovieController::class, 'showTrailer'])->name('movietrailers');

        Route::post('Movies/{id?}', [MovieController::class, 'showMovies'])->name('Movies');

        Route::post('movie', [MovieController::class, 'showMovies'])->name('movie');

        Route::post('movietrailers', [MovieController::class, 'showTrailer'])->name('movietrailers');

        Route::get('Audio/{id?}', [MusicController::class, 'show'])->name('Audio');

        Route::post('Audio/{id?}', [MusicController::class, 'show'])->name('Audio');

        Route::get('Console/{id?}', [ConsoleController::class, 'show'])->name('Console');

        Route::post('Console/{id?}', [ConsoleController::class, 'show'])->name('Console');

        Route::get('XXX/{id?}', [AdultController::class, 'show'])->name('XXX');

        Route::post('XXX/{id?}', [AdultController::class, 'show'])->name('XXX');

        Route::get('anime', [AnimeController::class, 'showAnime'])->name('anime');
        Route::post('anime', [AnimeController::class, 'showAnime'])->name('anime');

        Route::get('animelist', [AnimeController::class, 'showList'])->name('animelist');
        Route::post('animelist', [AnimeController::class, 'showList'])->name('animelist');

        Route::get('Books/{id?}', [BooksController::class, 'index'])->name('Books');
        Route::post('Books/{id?}', [BooksController::class, 'index'])->name('Books');
    });

    Route::get('nfo/{id?}', [NfoController::class, 'showNfo'])->name('nfo');

    Route::post('nfo/{id?}', [NfoController::class, 'showNfo'])->name('nfo');

    Route::get('contact-us', [ContactUsController::class, 'showContactForm'])->name('contact-us');
    Route::post('contact-us', [ContactUsController::class, 'contact'])->name('contact-us');

    Route::get('forum', [ForumController::class, 'forum'])->name('forum');

    Route::post('forum', [ForumController::class, 'forum'])->name('forum');

    Route::get('forumpost/{id}', [ForumController::class, 'getPosts'])->name('forumpost');

    Route::post('forumpost/{id}', [ForumController::class, 'getPosts'])->name('forumpost');

    Route::get('topic_delete', [ForumController::class, 'deleteTopic'])->name('topic_delete');

    Route::post('topic_delete', [ForumController::class, 'deleteTopic'])->name('topic_delete');

    Route::get('post_edit', [ForumController::class, 'edit'])->name('post_edit');

    Route::post('post_edit', [ForumController::class, 'edit'])->name('post_edit');

    Route::get('profileedit', [ProfileController::class, 'edit'])->name('profileedit');

    Route::post('profileedit', [ProfileController::class, 'edit'])->name('profileedit');

    Route::get('profile_delete', [ProfileController::class, 'destroy'])->name('profile_delete');

    Route::post('profile_delete', [ProfileController::class, 'destroy'])->name('profile_delete');

    Route::get('search', [SearchController::class, 'search'])->name('search');

    Route::post('search', [SearchController::class, 'search'])->name('search');

    Route::get('mymovies', [MyMoviesController::class, 'show'])->name('mymovies');

    Route::post('mymovies', [MyMoviesController::class, 'show'])->name('mymovies');

    Route::get('myshows', [MyShowsController::class, 'show'])->name('myshows');

    Route::post('myshows', [MyShowsController::class, 'show'])->name('myshows');

    Route::get('myshows/browse', [MyShowsController::class, 'browse']);

    Route::post('myshows/browse', [MyShowsController::class, 'browse']);

    Route::get('filelist/{guid}', [FileListController::class, 'show']);

    Route::post('filelist/{guid}', [FileListController::class, 'show']);

    Route::get('btc_payment', [BtcPaymentController::class, 'show'])->name('btc_payment');

    Route::post('btc_payment', [BtcPaymentController::class, 'show'])->name('btc_payment');

    Route::get('btc_payment_callback', [BtcPaymentController::class, 'callback'])->name('btc_payment_callback');

    Route::post('btc_payment_callback', [BtcPaymentController::class, 'callback'])->name('btc_payment_callback');

    Route::get('series/{id?}', [SeriesController::class, 'index'])->name('series');

    Route::post('series/{id?}', [SeriesController::class, 'index'])->name('series');

    Route::get('ajax_profile', [AjaxController::class, 'profile']);

    Route::post('ajax_profile', [AjaxController::class, 'profile']);

    Route::get('2fa', [PasswordSecurityController::class, 'show2faForm']);
    Route::post('generate2faSecret', [PasswordSecurityController::class, 'generate2faSecret'])->name('generate2faSecret');
    Route::post('2fa', [PasswordSecurityController::class, 'enable2fa'])->name('enable2fa');
    Route::post('disable2fa', [PasswordSecurityController::class, 'disable2fa'])->name('disable2fa');
});

Route::get('forum-delete/{id}', [ForumController::class, 'destroy'])->middleware('role:Admin');

Route::post('forum-delete/{id}', [ForumController::class, 'destroy'])->middleware('role:Admin');

Route::middleware('role:Admin', '2fa')->prefix('admin')->group(function () {
    Route::get('index', [AdminPageController::class, 'index']);
    Route::get('anidb-delete/{id}', [AdminAnidbController::class, 'destroy']);
    Route::post('anidb-delete/{id}', [AdminAnidbController::class, 'destroy']);
    Route::get('anidb-edit/{id}', [AdminAnidbController::class, 'edit']);
    Route::post('anidb-edit/{id}', [AdminAnidbController::class, 'edit']);
    Route::get('anidb-list', [AdminAnidbController::class, 'index']);
    Route::post('anidb-list', [AdminAnidbController::class, 'index']);
    Route::get('binaryblacklist-list', [AdminBlacklistController::class, 'index']);
    Route::post('binaryblacklist-list', [AdminBlacklistController::class, 'index']);
    Route::get('binaryblacklist-edit', [AdminBlacklistController::class, 'edit']);
    Route::post('binaryblacklist-edit', [AdminBlacklistController::class, 'edit']);
    Route::get('book-list', [AdminBookController::class, 'index']);
    Route::post('book-list', [AdminBookController::class, 'index']);
    Route::get('book-edit', [AdminBookController::class, 'edit']);
    Route::post('book-edit', [AdminBookController::class, 'edit']);
    Route::get('category-list', [AdminCategoryController::class, 'index']);
    Route::post('category-list', [AdminCategoryController::class, 'index']);
    Route::get('category-edit', [AdminCategoryController::class, 'edit']);
    Route::post('category-edit', [AdminCategoryController::class, 'edit']);
    Route::get('user-list', [AdminUserController::class, 'index']);
    Route::post('user-list', [AdminUserController::class, 'index']);
    Route::get('user-edit', [AdminUserController::class, 'edit']);
    Route::post('user-edit', [AdminUserController::class, 'edit']);
    Route::get('user-delete', [AdminUserController::class, 'destroy']);
    Route::post('user-delete', [AdminUserController::class, 'destroy']);
    Route::get('verify', [AdminUserController::class, 'verify']);
    Route::post('verify', [AdminUserController::class, 'verify']);
    Route::get('resendverification', [AdminUserController::class, 'resendVerification']);
    Route::post('resendverification', [AdminUserController::class, 'resendVerification']);
    Route::get('site-edit', [AdminSiteController::class, 'edit']);
    Route::post('site-edit', [AdminSiteController::class, 'edit']);
    Route::get('site-stats', [AdminSiteController::class, 'stats']);
    Route::post('site-stats', [AdminSiteController::class, 'stats']);
    Route::get('role-list', [AdminRoleController::class, 'index']);
    Route::post('role-list', [AdminRoleController::class, 'index']);
    Route::get('role-add', [AdminRoleController::class, 'create']);
    Route::post('role-add', [AdminRoleController::class, 'create']);
    Route::get('role-edit', [AdminRoleController::class, 'edit']);
    Route::post('role-edit', [AdminRoleController::class, 'edit']);
    Route::get('role-delete', [AdminRoleController::class, 'destroy']);
    Route::post('role-delete', [AdminRoleController::class, 'destroy']);
    Route::get('content-list', [AdminContentController::class, 'index']);
    Route::post('content-list', [AdminContentController::class, 'index']);
    Route::get('content-add', [AdminContentController::class, 'create']);
    Route::post('content-add', [AdminContentController::class, 'create']);
    Route::get('content-delete', [AdminContentController::class, 'destroy']);
    Route::post('content-delete', [AdminContentController::class, 'destroy']);
    Route::get('category_regexes-list', [AdminCategoryRegexesController::class, 'index']);
    Route::post('category_regexes-list', [AdminCategoryRegexesController::class, 'index']);
    Route::get('category_regexes-edit', [AdminCategoryRegexesController::class, 'edit']);
    Route::post('category_regexes-edit', [AdminCategoryRegexesController::class, 'edit']);
    Route::get('collection_regexes-list', [AdminCollectionRegexesController::class, 'index']);
    Route::post('collection_regexes-list', [AdminCollectionRegexesController::class, 'index']);
    Route::get('collection_regexes-edit', [AdminCollectionRegexesController::class, 'edit']);
    Route::post('collection_regexes-edit', [AdminCollectionRegexesController::class, 'edit']);
    Route::get('collection_regexes-test', [AdminCollectionRegexesController::class, 'testRegex']);
    Route::post('collection_regexes-test', [AdminCollectionRegexesController::class, 'testRegex']);
    Route::get('release_naming_regexes-list', [AdminReleaseNamingRegexesController::class, 'index']);
    Route::post('release_naming_regexes-list', [AdminReleaseNamingRegexesController::class, 'index']);
    Route::get('release_naming_regexes-edit', [AdminReleaseNamingRegexesController::class, 'edit']);
    Route::post('release_naming_regexes-edit', [AdminReleaseNamingRegexesController::class, 'edit']);
    Route::get('release_naming_regexes-test', [AdminReleaseNamingRegexesController::class, 'testRegex']);
    Route::post('release_naming_regexes-test', [AdminReleaseNamingRegexesController::class, 'testRegex']);
    Route::get('ajax', [AdminAjaxController::class, 'ajaxAction']);
    Route::post('ajax', [AdminAjaxController::class, 'ajaxAction']);
    Route::get('tmux-edit', [AdminTmuxController::class, 'edit']);
    Route::post('tmux-edit', [AdminTmuxController::class, 'edit']);
    Route::get('release-list', [AdminReleasesController::class, 'index']);
    Route::post('release-list', [AdminReleasesController::class, 'index']);
    Route::get('release-delete/{id}', [AdminReleasesController::class, 'destroy']);
    Route::post('release-delete/{id}', [AdminReleasesController::class, 'destroy']);
    Route::get('show-list', [AdminShowsController::class, 'index']);
    Route::post('show-list', [AdminShowsController::class, 'index']);
    Route::get('show-edit', [AdminShowsController::class, 'edit']);
    Route::post('show-edit', [AdminShowsController::class, 'edit']);
    Route::get('show-remove', [AdminShowsController::class, 'destroy']);
    Route::post('show-remove', [AdminShowsController::class, 'destroy']);
    Route::get('comments-list', [AdminCommentsController::class, 'index']);
    Route::post('comments-list', [AdminCommentsController::class, 'index']);
    Route::get('comments-delete/{id}', [AdminCommentsController::class, 'destroy']);
    Route::post('comments-delete/{id}', [AdminCommentsController::class, 'destroy']);
    Route::get('console-list', [ConsoleController::class, 'index']);
    Route::post('console-list', [AdminConsoleController::class, 'index']);
    Route::get('console-edit', [AdminConsoleController::class, 'edit']);
    Route::post('console-edit', [AdminConsoleController::class, 'edit']);
    Route::get('failrel-list', [AdminFailedReleasesController::class, 'index']);
    Route::get('game-list', [AdminGameController::class, 'index']);
    Route::post('game-list', [AdminGameController::class, 'index']);
    Route::get('game-edit', [AdminGameController::class, 'edit']);
    Route::post('game-edit', [AdminGameController::class, 'edit']);
    Route::get('movie-list', [AdminMovieController::class, 'index']);
    Route::post('movie-list', [AdminMovieController::class, 'index']);
    Route::get('movie-edit', [AdminMovieController::class, 'edit']);
    Route::post('movie-edit', [AdminMovieController::class, 'edit']);
    Route::get('movie-add', [AdminMovieController::class, 'create']);
    Route::post('movie-add', [AdminMovieController::class, 'create']);
    Route::get('music-list', [AdminMusicController::class, 'index']);
    Route::post('music-list', [AdminMusicController::class, 'index']);
    Route::get('music-edit', [AdminMusicController::class, 'edit']);
    Route::post('music-edit', [AdminMusicController::class, 'edit']);
    Route::get('nzb-import', [AdminNzbController::class, 'import']);
    Route::post('nzb-import', [AdminNzbController::class, 'import']);
    Route::get('nzb-export', [AdminNzbController::class, 'export']);
    Route::post('nzb-export', [AdminNzbController::class, 'export']);
    Route::get('predb', [AdminPredbController::class, 'index']);
    Route::post('predb', [AdminPredbController::class, 'index']);
    Route::get('group-list', [AdminGroupController::class, 'index']);
    Route::post('group-list', [AdminGroupController::class, 'index']);
    Route::get('group-edit', [AdminGroupController::class, 'edit']);
    Route::post('group-edit', [AdminGroupController::class, 'edit']);
    Route::get('group-bulk', [AdminGroupController::class, 'createBulk']);
    Route::post('group-bulk', [AdminGroupController::class, 'createBulk']);
    Route::get('group-list-active', [AdminGroupController::class, 'active']);
    Route::post('group-list-active', [AdminGroupController::class, 'active']);
    Route::get('group-list-inactive', [AdminGroupController::class, 'inactive']);
    Route::post('group-list-inactive', [AdminGroupController::class, 'inactive']);
});

Route::middleware('role_or_permission:Admin|Moderator|edit release')->prefix('admin')->group(function () {
    Route::get('release-edit', [AdminReleasesController::class, 'edit']);
    Route::post('release-edit', [AdminReleasesController::class, 'edit']);
});

Route::post('2faVerify', function () {
    return redirect()->to(URL()->previous());
})->name('2faVerify')->middleware('2fa');

Route::post('btcpay/webhook', function (Illuminate\Http\Request $request) {
    $hashCheck = 'sha256='.hash_hmac('sha256', $request->getContent(), config('nntmux.btcpay_webhook_secret'));
    if ($hashCheck !== $request->header('btcpay-sig')) {
        Log::error('BTCPay webhook hash check failed: '.$request->header('btcpay-sig'));

        return response('Not Found', 404);
    }
    $payload = json_decode($request->getContent(), true);
    // We have received a payment for an invoice and user should be upgraded to a paid plan based on order
    if ($payload['type'] === 'InvoiceReceivedPayment') {
        preg_match('/(?P<role>\w+(\+\+)?)[ ](?P<addYears>\d+)/i', $payload['metadata']['itemDesc'], $matches);
        $user = User::query()->where('email', '=', $payload['metadata']['buyerEmail'])->first();
        if ($user) {
            User::updateUserRole($user->id, $matches['role']);
            User::updateUserRoleChangeDate($user->id, null, $matches['addYears']);
            Log::info('User upgraded to '.$matches['role'].' for BTCPay webhook: '.$payload['metadata']['buyerEmail']);
        } else {
            Log::error('User not found for BTCPay webhook: '.$payload['metadata']['buyerEmail']);
        }
    }

    return response('OK', 200);
});
