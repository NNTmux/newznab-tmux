<?php

namespace App\Services;

use App\Models\Settings;
use App\Services\AdultProcessing\AdultProcessingPipeline;

class XXXProcessor
{
    private bool $echooutput;

    public function __construct(bool $echooutput)
    {
        $this->echooutput = $echooutput;
    }

    public function process(): void
    {
        if ((int) Settings::settingValue('lookupxxx') === 1) {
            $pipeline = new AdultProcessingPipeline([], $this->echooutput);
            $pipeline->processXXXReleases();
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

