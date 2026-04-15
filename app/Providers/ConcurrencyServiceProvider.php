<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Concurrency\TimeoutAwareProcessDriver;
use Illuminate\Concurrency\ConcurrencyManager;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\ServiceProvider;

class ConcurrencyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->afterResolving(ConcurrencyManager::class, function (ConcurrencyManager $manager): void {
            $manager->extend('process', function (): TimeoutAwareProcessDriver {
                $configuredTimeout = config('nntmux.concurrency_timeout');
                $timeout = (int) ($configuredTimeout ?? config('nntmux.multiprocessing_max_child_time', 1800));

                return new TimeoutAwareProcessDriver(
                    app(ProcessFactory::class),
                    $timeout
                );
            });
        });
    }
}
