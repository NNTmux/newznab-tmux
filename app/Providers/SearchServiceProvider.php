<?php

namespace App\Providers;

use App\Services\Search\Contracts\SearchServiceInterface;
use App\Services\Search\ElasticSearchService;
use App\Services\Search\ManticoreSearchService;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ManticoreSearchService as singleton
        $this->app->singleton(ManticoreSearchService::class, function ($app) {
            return new ManticoreSearchService();
        });

        // Register ElasticSearchService as singleton
        $this->app->singleton(ElasticSearchService::class, function ($app) {
            return new ElasticSearchService();
        });

        // Bind the interface to the appropriate implementation based on config
        $this->app->singleton(SearchServiceInterface::class, function ($app) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                return $app->make(ElasticSearchService::class);
            }

            return $app->make(ManticoreSearchService::class);
        });

        // Register alias for ManticoreSearch (for backward compatibility)
        $this->app->alias(ManticoreSearchService::class, 'manticore');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

