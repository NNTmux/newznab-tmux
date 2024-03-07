<?php

namespace App\Providers;

use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Events\Registered;
use App\Listeners\UpdateUserLoggedIn;
use App\Listeners\UpdateUserAccessedApi;
use App\Events\UserLoggedIn;
use App\Events\UserAccessedApi;
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

        $this->bootEvent();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function bootEvent(): void
    {
        parent::boot();

        //
    }
}
