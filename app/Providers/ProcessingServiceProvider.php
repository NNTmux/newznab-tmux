<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\PostProcessService;
use App\Services\ReleaseProcessingService;
use Illuminate\Support\ServiceProvider;

class ProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PostProcessService::class, function ($app) {
            return new PostProcessService();
        });

        $this->app->singleton(ReleaseProcessingService::class, function ($app) {
            return new ReleaseProcessingService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

