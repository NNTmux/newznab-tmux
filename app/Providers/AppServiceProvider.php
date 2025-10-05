<?php

namespace App\Providers;

use App\Models\User;
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
        $smarty = app('smarty.view');
        view()->share('smarty', $smarty);
        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('Admin');
        });
        Event::listen(Login::class, LoginViaRemember::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
