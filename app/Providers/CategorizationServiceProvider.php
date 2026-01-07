<?php

namespace App\Providers;

use App\Services\Categorization\CategorizationPipeline;
use Illuminate\Support\ServiceProvider;

class CategorizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the pipeline as a singleton
        $this->app->singleton(CategorizationPipeline::class, function ($app) {
            return CategorizationPipeline::createDefault();
        });

        // Alias for easier access
        $this->app->alias(CategorizationPipeline::class, 'categorization');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
