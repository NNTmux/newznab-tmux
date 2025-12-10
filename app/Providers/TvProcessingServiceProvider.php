<?php

namespace App\Providers;

use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Support\ServiceProvider;

class TvProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TvProcessingPipeline::class, function ($app) {
            return TvProcessingPipeline::createDefault();
        });

        $this->app->alias(TvProcessingPipeline::class, 'tv-processing');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

