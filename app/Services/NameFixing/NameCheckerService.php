<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Services\NameFixing\Checkers\AppNameChecker;
use App\Services\NameFixing\Checkers\GameNameChecker;
use App\Services\NameFixing\Checkers\MovieNameChecker;
use App\Services\NameFixing\Checkers\TvNameChecker;
use App\Services\NameFixing\Contracts\NameCheckerInterface;
use App\Services\NameFixing\DTO\NameFixResult;
use Illuminate\Support\Collection;

/**
 * Service for checking release names against multiple patterns.
 *
 * Orchestrates multiple name checkers (TV, Movie, Game, App) to find
 * potential name fixes for releases.
 */
class NameCheckerService
{
    /**
     * Collection of registered name checkers.
     *
     * @var Collection<int, NameCheckerInterface>
     */
    protected Collection $checkers;

    /**
     * Whether checkers are sorted by priority.
     */
    protected bool $sorted = false;

    public function __construct()
    {
        $this->checkers = new Collection;
        $this->registerDefaultCheckers();
    }

    /**
     * Register the default name checkers.
     */
    protected function registerDefaultCheckers(): void
    {
        $this->addChecker(new TvNameChecker);
        $this->addChecker(new MovieNameChecker);
        $this->addChecker(new GameNameChecker);
        $this->addChecker(new AppNameChecker);
    }

    /**
     * Add a name checker to the service.
     */
    public function addChecker(NameCheckerInterface $checker): self
    {
        $this->checkers->push($checker);
        $this->sorted = false;

        return $this;
    }

    /**
     * Check a release name against all registered checkers.
     *
     * @param  object  $release  The release object
     * @param  string  $textstring  The text to check
     * @return NameFixResult|null The first matching result, or null if no match
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        $this->ensureSorted();

        foreach ($this->checkers as $checker) {
            $result = $checker->check($release, $textstring);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Check all registered checkers and return all matches.
     *
     * Useful for debugging or when you want to see all potential matches.
     *
     * @param  object  $release  The release object
     * @param  string  $textstring  The text to check
     * @return Collection<int, NameFixResult> All matching results
     */
    public function checkAll(object $release, string $textstring): Collection
    {
        $this->ensureSorted();
        $results = new Collection;

        foreach ($this->checkers as $checker) {
            $result = $checker->check($release, $textstring);
            if ($result !== null) {
                $results->push($result);
            }
        }

        return $results;
    }

    /**
     * Check using a specific checker by name.
     *
     * @param  string  $checkerName  The name of the checker to use
     * @param  object  $release  The release object
     * @param  string  $textstring  The text to check
     * @return NameFixResult|null The result if matched, null otherwise
     */
    public function checkWith(string $checkerName, object $release, string $textstring): ?NameFixResult
    {
        $checker = $this->checkers->first(
            fn (NameCheckerInterface $c) => strtolower($c->getName()) === strtolower($checkerName)
        );

        return $checker?->check($release, $textstring);
    }

    /**
     * Get all registered checkers.
     *
     * @return Collection<int, NameCheckerInterface>
     */
    public function getCheckers(): Collection
    {
        $this->ensureSorted();

        return $this->checkers;
    }

    /**
     * Get checker statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->checkers->map(fn (NameCheckerInterface $checker) => [
            'name' => $checker->getName(),
            'priority' => $checker->getPriority(),
            'class' => get_class($checker),
        ])->toArray();
    }

    /**
     * Ensure checkers are sorted by priority.
     */
    protected function ensureSorted(): void
    {
        if (! $this->sorted) {
            $this->checkers = $this->checkers->sortBy(
                fn (NameCheckerInterface $checker) => $checker->getPriority()
            )->values();
            $this->sorted = true;
        }
    }
}
