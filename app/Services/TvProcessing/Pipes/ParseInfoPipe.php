<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Blacklight\processing\tv\LocalDB;
use Closure;

/**
 * Initial pipe that parses the release name into structured info.
 * This must run before any provider pipes.
 */
class ParseInfoPipe extends AbstractTvProviderPipe
{
    protected int $priority = 1;
    private ?LocalDB $localDb = null;

    public function getName(): string
    {
        return 'ParseInfo';
    }

    public function getStatusCode(): int
    {
        return 0;
    }

    /**
     * Get or create the LocalDB instance for parsing.
     */
    private function getLocalDb(): LocalDB
    {
        if ($this->localDb === null) {
            $this->localDb = new LocalDB();
        }
        return $this->localDb;
    }

    /**
     * Override handle to perform parsing before the standard processing flow.
     */
    public function handle(TvProcessingPassable $passable, Closure $next): TvProcessingPassable
    {
        $parsedInfo = $this->getLocalDb()->parseInfo($passable->context->searchName);

        if ($parsedInfo === false || empty($parsedInfo['name'])) {
            // Mark as parse failed
            $passable->setParsedInfo(null);
            $passable->updateResult(
                TvProcessingResult::parseFailed(['search_name' => $passable->context->searchName]),
                $this->getName()
            );

            if ($this->echoOutput) {
                $this->colorCli->error(sprintf(
                    '  ✗ Parse failed: %s',
                    mb_substr($passable->context->searchName, 0, 50)
                ));
            }

            // Don't continue to other pipes - can't process without parsed info
            return $passable;
        }

        $passable->setParsedInfo($parsedInfo);

        return $next($passable);
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        // Not used - we override handle() instead
        return TvProcessingResult::pending();
    }
}
