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
use App\Http\Controllers\Admin\DeletedUsersController;
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
use App\Http\Controllers\GamesController;
use App\Http\Controllers\GetNzbController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\MyMoviesController;
use App\Http\Controllers\MyShowsController;
use App\Http\Controllers\NfoController;
use App\Http\Controllers\PasswordSecurityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSecurityController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\TermsController;

// Serve cover images from storage - Must be public (no auth required)
Route::get('/covers/{type}/{filename}', [App\Http\Controllers\CoverController::class, 'show'])
    ->where('type', 'anime|audio|audiosample|book|console|games|movies|music|preview|sample|tvrage|video|xxx')
    ->where('filename', '.*')
    ->name('covers.show');

// Auth::routes();

Route::match(['GET', 'POST'], '/', [ContentController::class, 'show'])->name('home');

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register'])->name('register.post');

Route::match(['GET', 'POST'], 'forgottenpassword', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('forgottenpassword')->withoutMiddleware(['auth', 'VerifyCsrfToken', 'web']);
Route::match(['GET', 'POST'], 'terms-and-conditions', [TermsController::class, 'terms'])->name('terms-and-conditions');

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login'])->name('login.post');
Route::match(['GET', 'POST'], 'logout', [LoginController::class, 'logout'])->name('logout');

Route::get('2fa/verify', [PasswordSecurityController::class, 'getVerify2fa'])->name('2fa.verify');
Route::post('2faVerify', [PasswordSecurityController::class, 'verify2fa'])->name('2faVerify');

Route::middleware('isVerified')->group(function () {
    Route::match(['GET', 'POST'], 'resetpassword', [ResetPasswordController::class, 'reset'])->name('resetpassword');
    Route::match(['GET', 'POST'], 'profile', [ProfileController::class, 'show'])->name('profile');

    Route::prefix('browse')->group(function () {
        Route::match(['GET', 'POST'], 'tags', [BrowseController::class, 'tags'])->name('tags');
        Route::match(['GET', 'POST'], 'group', [BrowseController::class, 'group'])->name('group');
        Route::match(['GET', 'POST'], 'All', [BrowseController::class, 'index'])->name('All');
        Route::match(['GET', 'POST'], '{parentCategory}/{id?}', [BrowseController::class, 'show'])->middleware('clearance')->name('browse');
    });

    Route::prefix('cart')->group(function () {
        Route::match(['GET', 'POST'], 'index', [CartController::class, 'index'])->name('cart.index');
        Route::match(['GET', 'POST'], 'add', [CartController::class, 'store'])->name('cart.add');
        Route::match(['GET', 'POST'], 'delete/{id}', [CartController::class, 'destroy'])->name('cart.delete');
    });

    Route::match(['GET', 'POST'], 'details/{guid}', [DetailsController::class, 'show'])->name('details');
    Route::match(['GET', 'POST'], 'getnzb/{guid}', [GetNzbController::class, 'getNzb'])->name('getnzb.guid');
    Route::match(['GET', 'POST'], 'getnzb', [GetNzbController::class, 'getNzb'])->name('getnzb');
    Route::match(['GET', 'POST'], 'rsshelp', [RssController::class, 'showRssDesc'])->name('rsshelp');
    Route::match(['GET', 'POST'], 'profile', [ProfileController::class, 'show'])->name('profile');
    Route::match(['GET', 'POST'], 'apihelp', [ApiHelpController::class, 'index'])->name('apihelp');
    Route::match(['GET', 'POST'], 'apiv2help', [ApiHelpController::class, 'apiv2'])->name('apiv2help');
    Route::match(['GET', 'POST'], 'browsegroup', [BrowseGroupController::class, 'show'])->name('browsegroup');
    Route::match(['GET', 'POST'], 'content', [ContentController::class, 'show'])->name('content');
    Route::match(['GET', 'POST'], 'failed', [FailedReleasesController::class, 'failed'])->name('failed');

    Route::middleware('clearance')->group(function () {
        Route::match(['GET', 'POST'], 'Games', [GamesController::class, 'show'])->name('Games');
        Route::match(['GET', 'POST'], 'movie/{imdbid}', [MovieController::class, 'showMovie'])->name('movie.view');
        Route::match(['GET', 'POST'], 'Movies/{id?}', [MovieController::class, 'showMovies'])->name('Movies');
        Route::match(['GET', 'POST'], 'movie', [MovieController::class, 'showMovies'])->name('movie');
        Route::match(['GET', 'POST'], 'movietrailers', [MovieController::class, 'showTrailer'])->name('movietrailers');
        Route::match(['GET', 'POST'], 'Audio/{id?}', [MusicController::class, 'show'])->name('Audio');
        Route::match(['GET', 'POST'], 'Console/{id?}', [ConsoleController::class, 'show'])->name('Console');
        Route::match(['GET', 'POST'], 'XXX/{id?}', [AdultController::class, 'show'])->name('XXX');
        Route::match(['GET', 'POST'], 'anime', [AnimeController::class, 'showAnime'])->name('anime');
        Route::match(['GET', 'POST'], 'animelist', [AnimeController::class, 'showList'])->name('animelist');
        Route::match(['GET', 'POST'], 'Books/{id?}', [BooksController::class, 'index'])->name('Books');
    });

    Route::match(['GET', 'POST'], 'nfo/{id?}', [NfoController::class, 'showNfo'])->name('nfo');

    Route::match(['GET', 'POST'], 'contact-us', [ContactUsController::class, 'showContactForm'])->name('contact-us');
    Route::post('contact-us', [ContactUsController::class, 'contact']);
    Route::match(['GET', 'POST'], 'profileedit', [ProfileController::class, 'edit'])->name('profileedit');
    Route::match(['GET', 'POST'], 'profile_delete', [ProfileController::class, 'destroy'])->name('profile_delete');
    Route::match(['GET', 'POST'], 'search', [SearchController::class, 'search'])->name('search');
    Route::match(['GET', 'POST'], 'mymovies', [MyMoviesController::class, 'show'])->name('mymovies');
    Route::match(['GET', 'POST'], 'myshows', [MyShowsController::class, 'show'])->name('myshows');
    Route::match(['GET', 'POST'], 'myshows/browse', [MyShowsController::class, 'browse'])->name('myshows.browse');
    Route::match(['GET', 'POST'], 'filelist/{guid}', [FileListController::class, 'show'])->name('filelist');
    Route::get('api/release/{guid}/filelist', [\App\Http\Controllers\Api\FileListApiController::class, 'getFileList'])->name('api.filelist');
    Route::match(['GET', 'POST'], 'series/{id?}', [SeriesController::class, 'index'])->name('series');
    Route::match(['GET', 'POST'], 'ajax_profile', [AjaxController::class, 'profile'])->name('ajax_profile');
    Route::match(['GET', 'POST'], '2fa', [PasswordSecurityController::class, 'show2faForm'])->name('2fa');
    Route::get('2fa/enable', [PasswordSecurityController::class, 'showEnable2faForm'])->name('2fa.enable');
    Route::get('2fa/disable', [PasswordSecurityController::class, 'showDisable2faForm'])->name('2fa.disable');
    Route::post('generate2faSecret', [PasswordSecurityController::class, 'generate2faSecret'])->name('generate2faSecret');
    Route::post('2fa', [PasswordSecurityController::class, 'enable2fa'])->name('enable2fa');
    Route::post('disable2fa', [PasswordSecurityController::class, 'disable2fa'])->name('disable2fa');
    Route::post('profile-disable2fa', [PasswordSecurityController::class, 'profileDisable2fa'])->name('profile-disable2fa');
    // Custom 2FA routes that redirect to profile page
    Route::post('profileedit/enable2fa', [PasswordSecurityController::class, 'enable2fa'])->name('profileedit.enable2fa');
    Route::post('profileedit/disable2fa', [PasswordSecurityController::class, 'disable2fa'])->name('profileedit.disable2fa');
    Route::post('profileedit/cancel2fa', [PasswordSecurityController::class, 'cancelSetup'])->name('profileedit.cancel2fa');
    Route::post('profile-security/disable-2fa', [ProfileSecurityController::class, 'disable2fa'])->name('profile.security.disable2fa');
});

Route::middleware('role:Admin', '2fa')->prefix('admin')->group(function () {
    Route::match(['GET', 'POST'], 'index', [AdminPageController::class, 'index'])->name('admin.index');
    Route::match(['GET', 'POST'], 'anidb-delete/{id}', [AdminAnidbController::class, 'destroy'])->name('admin.anidb-delete');
    Route::match(['GET', 'POST'], 'anidb-edit/{id}', [AdminAnidbController::class, 'edit'])->name('admin.anidb-edit');
    Route::match(['GET', 'POST'], 'anidb-list', [AdminAnidbController::class, 'index'])->name('admin.anidb-list');
    Route::match(['GET', 'POST'], 'binaryblacklist-list', [AdminBlacklistController::class, 'index'])->name('admin.binaryblacklist-list');
    Route::match(['GET', 'POST'], 'binaryblacklist-edit', [AdminBlacklistController::class, 'edit'])->name('admin.binaryblacklist-edit');
    Route::match(['GET', 'POST'], 'book-list', [AdminBookController::class, 'index'])->name('admin.book-list');
    Route::match(['GET', 'POST'], 'book-edit', [AdminBookController::class, 'edit'])->name('admin.book-edit');
    Route::match(['GET', 'POST'], 'category-list', [AdminCategoryController::class, 'index'])->name('admin.category-list');
    Route::match(['GET', 'POST'], 'category-edit', [AdminCategoryController::class, 'edit'])->name('admin.category-edit');
    Route::match(['GET', 'POST'], 'user-list', [AdminUserController::class, 'index'])->name('admin.user-list');
    Route::match(['GET', 'POST'], 'user-edit', [AdminUserController::class, 'edit'])->name('admin.user-edit');
    Route::match(['GET', 'POST'], 'user-delete', [AdminUserController::class, 'destroy'])->name('admin.user-delete');
    Route::match(['GET', 'POST'], 'verify', [AdminUserController::class, 'verify'])->name('admin.verify');
    Route::match(['GET', 'POST'], 'resendverification', [AdminUserController::class, 'resendVerification'])->name('admin.resend-verification');
    Route::match(['GET', 'POST'], 'site-edit', [AdminSiteController::class, 'edit'])->name('admin.site-edit');
    Route::match(['GET', 'POST'], 'site-stats', [AdminSiteController::class, 'stats'])->name('admin.site-stats');
    Route::match(['GET', 'POST'], 'role-list', [AdminRoleController::class, 'index'])->name('admin.role-list');
    Route::match(['GET', 'POST'], 'role-add', [AdminRoleController::class, 'create'])->name('admin.role-add');
    Route::match(['GET', 'POST'], 'role-edit', [AdminRoleController::class, 'edit'])->name('admin.role-edit');
    Route::match(['GET', 'POST'], 'role-delete', [AdminRoleController::class, 'destroy'])->name('admin.role-delete');
    Route::match(['GET', 'POST'], 'content-list', [AdminContentController::class, 'index'])->name('admin.content-list');
    Route::match(['GET', 'POST'], 'content-add', [AdminContentController::class, 'create'])->name('admin.content-add');
    Route::match(['GET', 'POST'], 'content-delete', [AdminContentController::class, 'destroy'])->name('admin.content-delete');
    Route::match(['GET', 'POST'], 'category_regexes-list', [AdminCategoryRegexesController::class, 'index'])->name('admin.category-regexes-list');
    Route::match(['GET', 'POST'], 'category_regexes-edit', [AdminCategoryRegexesController::class, 'edit'])->name('admin.category-regexes-edit');
    Route::match(['GET', 'POST'], 'collection_regexes-list', [AdminCollectionRegexesController::class, 'index'])->name('admin.collection-regexes-list');
    Route::match(['GET', 'POST'], 'collection_regexes-edit', [AdminCollectionRegexesController::class, 'edit'])->name('admin.collection-regexes-edit');
    Route::match(['GET', 'POST'], 'collection_regexes-test', [AdminCollectionRegexesController::class, 'testRegex'])->name('admin.collection-regexes-test');
    Route::match(['GET', 'POST'], 'release_naming_regexes-list', [AdminReleaseNamingRegexesController::class, 'index'])->name('admin.release-naming-regexes-list');
    Route::match(['GET', 'POST'], 'release_naming_regexes-edit', [AdminReleaseNamingRegexesController::class, 'edit'])->name('admin.release-naming-regexes-edit');
    Route::match(['GET', 'POST'], 'release_naming_regexes-test', [AdminReleaseNamingRegexesController::class, 'testRegex'])->name('admin.release-naming-regexes-test');
    Route::match(['GET', 'POST'], 'ajax', [AdminAjaxController::class, 'ajaxAction'])->name('admin.ajax');
    Route::match(['GET', 'POST'], 'tmux-edit', [AdminTmuxController::class, 'edit'])->name('admin.tmux-edit');
    Route::match(['GET', 'POST'], 'release-list', [AdminReleasesController::class, 'index'])->name('admin.release-list');
    Route::match(['GET', 'POST'], 'release-delete/{id}', [AdminReleasesController::class, 'destroy'])->name('admin.release-delete');
    Route::match(['GET', 'POST'], 'show-list', [AdminShowsController::class, 'index'])->name('admin.show-list');
    Route::match(['GET', 'POST'], 'show-edit', [AdminShowsController::class, 'edit'])->name('admin.show-edit');
    Route::match(['GET', 'POST'], 'show-remove', [AdminShowsController::class, 'destroy'])->name('admin.show-remove');
    Route::match(['GET', 'POST'], 'comments-list', [AdminCommentsController::class, 'index'])->name('admin.comments-list');
    Route::match(['GET', 'POST'], 'comments-delete/{id}', [AdminCommentsController::class, 'destroy'])->name('admin.comments-delete');
    Route::match(['GET', 'POST'], 'console-list', [AdminConsoleController::class, 'index'])->name('admin.console-list');
    Route::match(['GET', 'POST'], 'console-edit', [AdminConsoleController::class, 'edit'])->name('admin.console-edit');
    Route::match(['GET', 'POST'], 'failrel-list', [AdminFailedReleasesController::class, 'index'])->name('admin.failrel-list');
    Route::match(['GET', 'POST'], 'game-list', [AdminGameController::class, 'index'])->name('admin.game-list');
    Route::match(['GET', 'POST'], 'game-edit', [AdminGameController::class, 'edit'])->name('admin.game-edit');
    Route::match(['GET', 'POST'], 'movie-list', [AdminMovieController::class, 'index'])->name('admin.movie-list');
    Route::match(['GET', 'POST'], 'movie-edit', [AdminMovieController::class, 'edit'])->name('admin.movie-edit');
    Route::match(['GET', 'POST'], 'movie-add', [AdminMovieController::class, 'create'])->name('admin.movie-add');
    Route::match(['GET', 'POST'], 'music-list', [AdminMusicController::class, 'index'])->name('admin.music-list');
    Route::match(['GET', 'POST'], 'music-edit', [AdminMusicController::class, 'edit'])->name('admin.music-edit');
    Route::match(['GET', 'POST'], 'nzb-import', [AdminNzbController::class, 'import'])->name('admin.nzb-import');
    Route::match(['GET', 'POST'], 'nzb-export', [AdminNzbController::class, 'export'])->name('admin.nzb-export');
    Route::match(['GET', 'POST'], 'predb', [AdminPredbController::class, 'index'])->name('admin.predb');
    Route::match(['GET', 'POST'], 'group-list', [AdminGroupController::class, 'index'])->name('admin.group-list');
    Route::match(['GET', 'POST'], 'group-edit', [AdminGroupController::class, 'edit'])->name('admin.group-edit');
    Route::match(['GET', 'POST'], 'group-bulk', [AdminGroupController::class, 'createBulk'])->name('admin.group-bulk');
    Route::match(['GET', 'POST'], 'group-list-active', [AdminGroupController::class, 'active'])->name('admin.group-list-active');
    Route::match(['GET', 'POST'], 'group-list-inactive', [AdminGroupController::class, 'inactive'])->name('admin.group-list-inactive');

    // Deleted Users Management Routes
    Route::match(['GET', 'POST'], 'deleted-users', [DeletedUsersController::class, 'index'])->name('admin.deleted.users.index');
    Route::post('deleted-users/bulk', [DeletedUsersController::class, 'bulkAction'])->name('admin.deleted.users.bulk');
    Route::match(['GET', 'POST'], 'deleted-users/restore/{id}', [DeletedUsersController::class, 'restore'])->name('admin.deleted.users.restore');
    Route::match(['GET', 'POST'], 'deleted-users/permanent-delete/{id}', [DeletedUsersController::class, 'permanentDelete'])->name('admin.deleted.users.permanent-delete');
});

Route::middleware('role_or_permission:Admin|Moderator|edit release')->prefix('admin')->group(function () {
    Route::match(['GET', 'POST'], 'release-edit', [AdminReleasesController::class, 'edit'])->name('admin.release-edit');
});

// Invitation management routes
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::get('/', [InvitationController::class, 'index'])->name('index');
    Route::get('/create', [InvitationController::class, 'create'])->name('create');
    Route::post('/store', [InvitationController::class, 'store'])->name('store');
    Route::post('/{id}/resend', [InvitationController::class, 'resend'])->name('resend');
    Route::delete('/{id}', [InvitationController::class, 'destroy'])->name('destroy');
    Route::get('/stats', [InvitationController::class, 'stats'])->name('stats');
});

// Public invitation view (no auth required)
Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');

// Admin invitation management routes
Route::middleware('role:Admin', '2fa')->prefix('admin/invitations')->name('admin.invitations.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\AdminInvitationController::class, 'index'])->name('index');
    Route::get('/{id}', [App\Http\Controllers\Admin\AdminInvitationController::class, 'show'])->name('show');
    Route::post('/{id}/resend', [App\Http\Controllers\Admin\AdminInvitationController::class, 'resend'])->name('resend');
    Route::post('/{id}/cancel', [App\Http\Controllers\Admin\AdminInvitationController::class, 'cancel'])->name('cancel');
    Route::post('/bulk', [App\Http\Controllers\Admin\AdminInvitationController::class, 'bulkAction'])->name('bulk');
    Route::post('/cleanup', [App\Http\Controllers\Admin\AdminInvitationController::class, 'cleanup'])->name('cleanup');
});

Route::post('btcpay/webhook', [BtcPaymentController::class, 'btcPayCallback'])->name('btcpay.webhook');
