<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ReleaseNameFixed;
use App\Listeners\RecategorizeReleaseAfterNameFix;
use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\BookInfo;
use App\Models\Category;
use App\Models\ConsoleInfo;
use App\Models\GamesInfo;
use App\Models\MovieInfo;
use App\Models\MusicInfo;
use App\Models\Release;
use App\Models\RolePromotion;
use App\Models\RootCategory;
use App\Models\SteamApp;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\Video;
use App\Observers\AnidbInfoObserver;
use App\Observers\AnidbTitleObserver;
use App\Observers\BookInfoObserver;
use App\Observers\CategoryObserver;
use App\Observers\ConsoleInfoObserver;
use App\Observers\GamesInfoObserver;
use App\Observers\MovieInfoObserver;
use App\Observers\MusicInfoObserver;
use App\Observers\ReleaseObserver;
use App\Observers\RolePromotionObserver;
use App\Observers\RootCategoryObserver;
use App\Observers\SteamAppObserver;
use App\Observers\UsenetGroupObserver;
use App\Observers\VideoObserver;
use App\View\Composers\AdminDataComposer;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FALaravel\Listeners\LoginViaRemember;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // Share public-site global data with layouts that need menu/category data.
        // search.* needs direct registration because @section blocks are evaluated
        // before the layout composer runs.
        view()->composer([
            'layouts.main',
            'layouts.guest',
            'layouts.app',
            'search.*',
        ], GlobalDataComposer::class);

        // Admin pages use a leaner composer and do not need public-site menu data.
        view()->composer([
            'layouts.admin',
            'admin.*',
        ], AdminDataComposer::class);

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('Admin');
        });
        Event::listen(Login::class, LoginViaRemember::class);
        Event::listen(ReleaseNameFixed::class, RecategorizeReleaseAfterNameFix::class);

        // Register observers
        RolePromotion::observe(RolePromotionObserver::class);
        MovieInfo::observe(MovieInfoObserver::class);
        Video::observe(VideoObserver::class);
        Release::observe(ReleaseObserver::class);
        Category::observe(CategoryObserver::class);
        RootCategory::observe(RootCategoryObserver::class);
        UsenetGroup::observe(UsenetGroupObserver::class);
        MusicInfo::observe(MusicInfoObserver::class);
        BookInfo::observe(BookInfoObserver::class);
        GamesInfo::observe(GamesInfoObserver::class);
        ConsoleInfo::observe(ConsoleInfoObserver::class);
        SteamApp::observe(SteamAppObserver::class);
        AnidbTitle::observe(AnidbTitleObserver::class);
        AnidbInfo::observe(AnidbInfoObserver::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
