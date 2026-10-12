<?php

declare(strict_types=1);

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
        // A fresh pipeline per resolve: pipes and providers hold per-run state.
        $this->app->bind(TvProcessingPipeline::class, static fn (): TvProcessingPipeline => TvProcessingPipeline::createDefault(
            (bool) config('nntmux.echocli')
        ));
    }
}
