<?php

namespace Blacklight\processing\post;

use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\ArchiveExtractionService;
use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\MediaExtractionService;
use App\Services\AdditionalProcessing\NzbContentParser;
use App\Services\AdditionalProcessing\ReleaseFileManager;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\Categorization\CategorizationService;
use App\Services\TempWorkspaceService;
use App\Services\NameFixing\NameFixingService;
use Blacklight\Nfo;
use Blacklight\NZB;
use Blacklight\ReleaseExtra;
use Blacklight\ReleaseImage;
use Exception;

/**
 * @deprecated Use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator instead.
 *             This class is maintained for backward compatibility only.
 *
 * Additional release post-processing.
 * Handles NZB parsing, archive extraction, media processing, and release updates.
 *
 * The functionality has been refactored into separate services:
 * - ProcessingConfiguration: Configuration management
 * - NzbContentParser: NZB file parsing
 * - ArchiveExtractionService: Archive handling (RAR, ZIP, 7z, etc.)
 * - MediaExtractionService: Video/audio/image processing
 * - UsenetDownloadService: NNTP downloads
 * - ReleaseFileManager: Database operations
 * - ConsoleOutputService: CLI output
 * - AdditionalProcessingOrchestrator: Main coordinator
 */
class ProcessAdditional
{
    /**
     * How many compressed (rar/zip) files to check.
     */
    public const int maxCompressedFilesToCheck = 10;

    private AdditionalProcessingOrchestrator $orchestrator;

    /**
     * ProcessAdditional constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        // Build the orchestrator with all dependencies
        $config = new ProcessingConfiguration();

        $this->orchestrator = new AdditionalProcessingOrchestrator(
            $config,
            new NzbContentParser(new NZB(), $config->debugMode, $config->echoCLI),
            new ArchiveExtractionService($config),
            new MediaExtractionService(
                $config,
                new ReleaseImage(),
                new ReleaseExtra(),
                new CategorizationService()
            ),
            new UsenetDownloadService($config),
            new ReleaseFileManager(
                $config,
                new ReleaseExtra(),
                new ReleaseImage(),
                new Nfo(),
                new NZB(),
                new NameFixingService()
            ),
            new TempWorkspaceService(),
            new ConsoleOutputService($config->echoCLI)
        );
    }

    /**
     * Start the additional processing.
     *
     * @throws Exception
     */
    public function start(string $groupID = '', string $guidChar = ''): void
    {
        $this->orchestrator->start($groupID, $guidChar);
    }

    /**
     * Process only a single release by GUID.
     */
    public function processSingleGuid(string $guid): bool
    {
        return $this->orchestrator->processSingleGuid($guid);
    }
}
