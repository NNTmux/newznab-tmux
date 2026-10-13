<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Closure;

/**
 * Initial pipe that parses the release name into structured info.
 * This must run before any provider pipes.
 */
class ParseInfoPipe extends AbstractTvProviderPipe
{
    protected int $priority = 1;

    public function __construct(private ?LocalDbProvider $parser = null) {}

    public function getName(): string
    {
        return 'ParseInfo';
    }

    public function handle(TvProcessingPassable $passable, Closure $next): TvProcessingPassable
    {
        $this->parser ??= new LocalDbProvider;
        $parsed = ParsedTvRelease::fromParseInfo($this->parser->parseInfo($passable->context->searchName));

        if ($parsed === null) {
            $passable->markParseFailed();
            $passable->recordProviderResult($this->getName(), TvProcessingResult::parseFailed());

            if ($this->echoOutput) {
                cli()->error(sprintf('  ✗ Parse failed: %s', mb_substr($passable->context->searchName, 0, 50)));
            }

            // Nothing else can work without parsed info.
            return $passable;
        }

        $passable->setParsed($parsed);

        return $next($passable);
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        // Not used - handle() does the parsing.
        return TvProcessingResult::pending();
    }
}
