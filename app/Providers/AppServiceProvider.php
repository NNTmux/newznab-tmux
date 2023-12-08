<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        $smarty = app('smarty.view');
        view()->share('smarty', $smarty);
        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('Admin');
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
