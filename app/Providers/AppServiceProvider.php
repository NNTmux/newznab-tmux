<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();
        $smarty = app('smarty.view');
        view()->share('smarty', $smarty);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
