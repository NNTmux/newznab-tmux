<?php

namespace App\Providers;

use App\Services\Search\Contracts\SearchDriverInterface;
use App\Services\Search\Contracts\SearchServiceInterface;
use App\Services\Search\Drivers\ElasticSearchDriver;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use App\Services\Search\MediaSearchService;
use App\Services\Search\SearchService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge the search configuration
        $this->mergeConfigFrom(
            base_path('config/search.php'),
            'search'
        );

        // Register the SearchService as a singleton (Manager pattern)
        $this->app->singleton(SearchService::class, function (Application $app) {
            return new SearchService($app);
        });

        // Bind the interface to the SearchService
        $this->app->singleton(SearchServiceInterface::class, function (Application $app) {
            return $app->make(SearchService::class);
        });

        // Bind SearchDriverInterface to current driver
        $this->app->bind(SearchDriverInterface::class, function (Application $app) {
            return $app->make(SearchService::class)->driver();
        });

        // Register the MediaSearchService for optimized movie/TV searches
        $this->app->singleton(MediaSearchService::class, function (Application $app) {
            return new MediaSearchService();
        });

        // Register individual drivers as singletons for direct access
        $this->app->singleton(ManticoreSearchDriver::class, function (Application $app) {
            $config = config('search.drivers.manticore', []);

            return new ManticoreSearchDriver($config);
        });

        $this->app->singleton(ElasticSearchDriver::class, function (Application $app) {
            $config = config('search.drivers.elasticsearch', []);

            return new ElasticSearchDriver($config);
        });

        // Register aliases (using prefixed names to avoid conflicts with other packages)
        $this->app->alias(SearchService::class, 'search');
        $this->app->alias(ManticoreSearchDriver::class, 'search.manticore');
        $this->app->alias(ElasticSearchDriver::class, 'search.elasticsearch');
        $this->app->alias(MediaSearchService::class, 'search.media');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

