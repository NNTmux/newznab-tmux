<?php

namespace App\Providers;

use App\Services\AdultProcessing\AdultProcessingPipeline;
use App\Services\AdultProcessing\Pipes\AdePipe;
use App\Services\AdultProcessing\Pipes\AdmPipe;
use App\Services\AdultProcessing\Pipes\AebnPipe;
use App\Services\AdultProcessing\Pipes\Data18Pipe;
use App\Services\AdultProcessing\Pipes\HotmoviesPipe;
use App\Services\AdultProcessing\Pipes\IafdPipe;
use App\Services\AdultProcessing\Pipes\PoppornPipe;
use Illuminate\Support\ServiceProvider;

class AdultProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register individual pipes as singletons
        $this->app->singleton(AebnPipe::class, fn () => new AebnPipe);
        $this->app->singleton(IafdPipe::class, fn () => new IafdPipe);
        $this->app->singleton(Data18Pipe::class, fn () => new Data18Pipe);
        $this->app->singleton(PoppornPipe::class, fn () => new PoppornPipe);
        $this->app->singleton(AdmPipe::class, fn () => new AdmPipe);
        $this->app->singleton(AdePipe::class, fn () => new AdePipe);
        $this->app->singleton(HotmoviesPipe::class, fn () => new HotmoviesPipe);

        // Register the pipeline with all default pipes
        $this->app->singleton(AdultProcessingPipeline::class, function ($app) {
            return new AdultProcessingPipeline([
                $app->make(AebnPipe::class),
                $app->make(IafdPipe::class),
                $app->make(Data18Pipe::class),
                $app->make(PoppornPipe::class),
                $app->make(AdmPipe::class),
                $app->make(AdePipe::class),
                $app->make(HotmoviesPipe::class),
            ]);
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
