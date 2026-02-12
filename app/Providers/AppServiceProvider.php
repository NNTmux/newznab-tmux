<?php

namespace App\Providers;

use App\Models\MovieInfo;
use App\Models\Release;
use App\Models\RolePromotion;
use App\Models\User;
use App\Models\Video;
use App\Observers\MovieInfoObserver;
use App\Observers\ReleaseObserver;
use App\Observers\RolePromotionObserver;
use App\Observers\VideoObserver;
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

        // Share global data only with layouts and partials that need it
        // (avoids running queries for emails, components, and other minimal views)
        view()->composer([
            'layouts.main',
            'layouts.admin',
            'layouts.guest',
            'layouts.app',
        ], \App\View\Composers\GlobalDataComposer::class);

        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('Admin');
        });
        Event::listen(Login::class, LoginViaRemember::class);

        // Register observers
        RolePromotion::observe(RolePromotionObserver::class);
        MovieInfo::observe(MovieInfoObserver::class);
        Video::observe(VideoObserver::class);
        Release::observe(ReleaseObserver::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
