<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserServiceObserver;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        User::observe(UserServiceObserver::class);
    }
}
