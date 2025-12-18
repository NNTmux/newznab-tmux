<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use App\Services\TvProcessing\TvReleaseContext;
use Blacklight\ColorCLI;
use Closure;

/**
 * Base class for TV processing pipe handlers.
 *
 * Each pipe is responsible for processing releases through a specific provider.
 */
abstract class AbstractTvProviderPipe
{
    protected int $priority = 50;
    protected bool $echoOutput = true;
    protected ColorCLI $colorCli;
    protected array $titleCache = [];

    public function __construct()
    {
        $this->colorCli = new ColorCLI();
    }

    /**
     * Handle the TV processing request.
     */
    public function handle(TvProcessingPassable $passable, Closure $next): TvProcessingPassable
    {
        // If we already have a match, skip processing
        if ($passable->shouldStopProcessing()) {
            return $next($passable);
        }

        // Skip if we don't have valid parsed info
        if (! $passable->hasValidParsedInfo()) {
            return $next($passable);
        }

        // Skip if this provider shouldn't process this release
        if ($this->shouldSkip($passable)) {
            $passable->updateResult(
                TvProcessingResult::skipped('Provider skipped', $this->getName()),
                $this->getName()
            );
            return $next($passable);
        }

        // Attempt to process with this provider
        $result = $this->process($passable);

        // Update the result
        $passable->updateResult($result, $this->getName());

        return $next($passable);
    }

    /**
     * Get the priority of this provider (lower = higher priority).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the name of this provider for debugging/logging.
     */
    abstract public function getName(): string;

    /**
     * Get the status code for releases awaiting this provider.
     */
    abstract public function getStatusCode(): int;

    /**
     * Attempt to process the release through this provider.
     */
    abstract protected function process(TvProcessingPassable $passable): TvProcessingResult;

    /**
     * Check if this provider should be skipped for the given passable.
     */
    protected function shouldSkip(TvProcessingPassable $passable): bool
    {
        return false;
    }

    /**
     * Set echo output flag.
     */
    public function setEchoOutput(bool $echo): self
    {
        $this->echoOutput = $echo;
        return $this;
    }

    /**
     * Check if a title is in the failure cache.
     */
    protected function isInTitleCache(string $title): bool
    {
        return in_array($title, $this->titleCache, true);
    }

    /**
     * Add a title to the failure cache.
     */
    protected function addToTitleCache(string $title): void
    {
        $this->titleCache[] = $title;
    }

    /**
     * Clear the title cache.
     */
    public function clearTitleCache(): void
    {
        $this->titleCache = [];
    }

    /**
     * Truncate title for display purposes.
     */
    protected function truncateTitle(string $title, int $maxLength = 45): string
    {
        if (mb_strlen($title) <= $maxLength) {
            return $title;
        }

        return mb_substr($title, 0, $maxLength - 3) . '...';
    }

    /**
     * Output match success message.
     */
    protected function outputMatch(string $title, ?int $season = null, ?int $episode = null, ?string $airdate = null): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->headerOver($this->truncateTitle($title));

        if ($airdate !== null) {
            $this->colorCli->primaryOver(' | ');
            $this->colorCli->warningOver($airdate);
        } elseif ($season !== null && $episode !== null) {
            $this->colorCli->primaryOver(' S');
            $this->colorCli->warningOver(sprintf('%02d', $season));
            $this->colorCli->primaryOver('E');
            $this->colorCli->warningOver(sprintf('%02d', $episode));
        }

        $this->colorCli->primaryOver(' ✓ ');
        $this->colorCli->primary('MATCHED (' . $this->getName() . ')');
    }

    /**
     * Output not found message.
     */
    protected function outputNotFound(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->alternateOver($this->truncateTitle($title));
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->alternate('Not found in ' . $this->getName());
    }

    /**
     * Output skipped message.
     */
    protected function outputSkipped(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->alternateOver($this->truncateTitle($title));
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->alternate('Skipped (previously failed)');
    }

    /**
     * Output searching message.
     */
    protected function outputSearching(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->headerOver($this->truncateTitle($title));
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->info('Searching ' . $this->getName() . '...');
    }

    /**
     * Output found in DB message.
     */
    protected function outputFoundInDb(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->headerOver($this->truncateTitle($title));
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->info('Found in DB');
    }
}

