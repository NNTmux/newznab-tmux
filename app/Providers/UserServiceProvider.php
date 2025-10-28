<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserActivityObserver;
use App\Observers\UserServiceObserver;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        User::observe(UserServiceObserver::class);
        User::observe(UserActivityObserver::class);
    }
}
