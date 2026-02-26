<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NfoService;
use App\Services\Nzb\NzbParserService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseExtraService;
use App\Services\ReleaseImageService;
use App\Services\TempWorkspaceService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Additional Processing services.
 * Registers all the refactored processing services with proper dependency injection.
 */
class AdditionalProcessingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Configuration is a singleton since it loads settings once
        $this->app->singleton(ProcessingConfiguration::class, function () {
            return new ProcessingConfiguration;
        });

        // Release extra service for video/audio/subtitle data
        $this->app->singleton(ReleaseExtraService::class, function () {
            return new ReleaseExtraService;
        });

        // Console output service
        $this->app->singleton(ConsoleOutputService::class, function ($app) {
            $config = $app->make(ProcessingConfiguration::class);

            return new ConsoleOutputService($config->echoCLI);
        });

        // NZB content parser
        $this->app->singleton(NzbContentParser::class, function ($app) {
            $config = $app->make(ProcessingConfiguration::class);

            return new NzbContentParser(
                $app->make(NzbService::class),
                $app->make(NzbParserService::class),
                $config->debugMode,
                $config->echoCLI
            );
        });

        // Archive extraction service
        $this->app->singleton(ArchiveExtractionService::class, function ($app) {
            return new ArchiveExtractionService(
                $app->make(ProcessingConfiguration::class)
            );
        });

        // Usenet download service
        $this->app->singleton(UsenetDownloadService::class, function ($app) {
            return new UsenetDownloadService(
                $app->make(ProcessingConfiguration::class)
            );
        });

        // Release file manager
        $this->app->singleton(ReleaseFileManager::class, function ($app) {
            return new ReleaseFileManager(
                $app->make(ProcessingConfiguration::class),
                new ReleaseImageService,
                new NfoService,
                $app->make(NzbService::class),
                new NameFixingService
            );
        });

        // Media extraction service
        $this->app->singleton(MediaExtractionService::class, function ($app) {
            return new MediaExtractionService(
                $app->make(ProcessingConfiguration::class),
                new ReleaseImageService,
                $app->make(ReleaseExtraService::class),
                new CategorizationService
            );
        });

        // Temp workspace service (might already be registered elsewhere)
        $this->app->singleton(TempWorkspaceService::class, function () {
            return new TempWorkspaceService;
        });

        // Main orchestrator
        $this->app->singleton(AdditionalProcessingOrchestrator::class, function ($app) {
            return new AdditionalProcessingOrchestrator(
                $app->make(ProcessingConfiguration::class),
                $app->make(NzbContentParser::class),
                $app->make(ArchiveExtractionService::class),
                $app->make(MediaExtractionService::class),
                $app->make(UsenetDownloadService::class),
                $app->make(ReleaseFileManager::class),
                $app->make(TempWorkspaceService::class),
                $app->make(ConsoleOutputService::class)
            );
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
