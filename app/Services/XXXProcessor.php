<?php

namespace App\Services;

use App\Models\Settings;
use App\Services\AdultProcessing\AdultProcessingPipeline;
use Blacklight\XXX;

class XXXProcessor
{
    private bool $echooutput;
    private bool $usePipeline;

    public function __construct(bool $echooutput, bool $usePipeline = true)
    {
        $this->echooutput = $echooutput;
        $this->usePipeline = $usePipeline;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupxxx') === 1) {
            if ($this->usePipeline) {
                // Use the new pipeline-based processing with async support
                $pipeline = new AdultProcessingPipeline([], $this->echooutput);
                $pipeline->processXXXReleases();
            } else {
                // Fall back to legacy processing
                (new XXX)->processXXXReleases();
            }
        }
    }

    /**
     * Process a single movie title and return the result.
     *
     * @param string $movie Movie title to look up
     * @param bool $debug Whether to include debug information
     * @return array Processing result
     */
    public function lookupMovie(string $movie, bool $debug = false): array
    {
        $pipeline = new AdultProcessingPipeline([], $this->echooutput);
        return $pipeline->processMovie($movie, $debug);
    }
}

