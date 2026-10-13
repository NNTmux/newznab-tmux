<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Closure;

/**
 * Base class for TV processing pipe handlers.
 *
 * Each pipe records what it found on the passable and hands it on; nothing
 * further runs once a release is resolved (episode matched, season pack or parse failure).
 */
abstract class AbstractTvProviderPipe
{
    protected int $priority = 50;

    protected bool $echoOutput = true;

    /**
     * Handle the TV processing request.
     */
    public function handle(TvProcessingPassable $passable, Closure $next): TvProcessingPassable
    {
        if ($passable->isResolved() || $passable->parsed === null) {
            return $next($passable);
        }

        $passable->recordProviderResult($this->getName(), $this->process($passable));

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
     * Attempt to process the release through this provider.
     */
    abstract protected function process(TvProcessingPassable $passable): TvProcessingResult;

    public function setEchoOutput(bool $echo): self
    {
        $this->echoOutput = $echo;

        return $this;
    }

    protected function truncateTitle(string $title, int $maxLength = 45): string
    {
        if (mb_strlen($title) <= $maxLength) {
            return $title;
        }

        return mb_substr($title, 0, $maxLength - 3).'...';
    }

    protected function outputMatch(string $title, ParsedTvRelease $parsed): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, true);
        $this->outputEpisodeReference($parsed);
        cli()->primaryOver(' ✓ ');
        cli()->primary('MATCHED ('.$this->getName().')');
    }

    protected function outputSeasonPack(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, true);
        cli()->primaryOver(' → ');
        cli()->primary('Full season matched ('.$this->getName().')');
    }

    protected function outputEpisodeMissing(string $title, ParsedTvRelease $parsed): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, false);
        $this->outputEpisodeReference($parsed);
        cli()->primaryOver(' → ');
        cli()->warning('Episode not found in '.$this->getName());
    }

    protected function outputNotFound(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, false);
        cli()->primaryOver(' → ');
        cli()->alternate('Not found in '.$this->getName());
    }

    protected function outputSkipped(string $title, string $reason): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, false);
        cli()->primaryOver(' → ');
        cli()->alternate('Skipped in '.$this->getName().' ('.$reason.')');
    }

    protected function outputSearching(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, true);
        cli()->primaryOver(' → ');
        cli()->info('Searching '.$this->getName().'...');
    }

    protected function outputTransient(string $title, string $error): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->outputTitle($title, false);
        cli()->primaryOver(' → ');
        cli()->warning($this->getName().' unavailable, will retry: '.$this->truncateTitle($error, 80));
    }

    private function outputTitle(string $title, bool $highlight): void
    {
        cli()->primaryOver('    → ');
        if ($highlight) {
            cli()->headerOver($this->truncateTitle($title));
        } else {
            cli()->alternateOver($this->truncateTitle($title));
        }
    }

    private function outputEpisodeReference(ParsedTvRelease $parsed): void
    {
        if ($parsed->hasEpisodeNumbers()) {
            cli()->primaryOver(' S');
            cli()->warningOver(sprintf('%02d', $parsed->season));
            cli()->primaryOver('E');
            cli()->warningOver(sprintf('%02d', $parsed->episode));
        } elseif ($parsed->hasAirdate()) {
            cli()->primaryOver(' | ');
            cli()->warningOver((string) $parsed->airdate);
        }
    }
}
