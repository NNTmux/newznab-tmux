<?php

declare(strict_types=1);

namespace App\Support\Data;

use App\Support\Data\Factories\SteamGameDataFactory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Data Transfer Object for Steam Game data.
 *
 * Provides a type-safe representation of game data retrieved from the Steam API.
 * Reshaping/parsing logic lives in {@see SteamGameDataFactory}.
 */
#[TypeScript]
final class SteamGameData extends Data
{
    /**
     * @param  array<int, array{thumbnail: ?string, full: ?string}>  $screenshots
     * @param  array<int, array{id: ?int, name: ?string, thumbnail: ?string, webm: ?string, mp4: ?string}>  $movies
     * @param  array<int, string>  $developers
     * @param  array<int, string>  $genres
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $platforms
     * @param  array<string, array{minimum: ?string, recommended: ?string}>  $requirements
     * @param  array<int, int>  $dlcIds
     */
    public function __construct(
        public int $steamId,
        public string $title,
        public string $type = 'game',
        public ?string $description = null,
        public ?string $detailedDescription = null,
        public ?string $about = null,
        public ?string $coverUrl = null,
        public ?string $backdropUrl = null,
        public array $screenshots = [],
        public array $movies = [],
        public ?string $trailerUrl = null,
        public ?string $publisher = null,
        public array $developers = [],
        public ?string $releaseDate = null,
        public array $genres = [],
        public array $categories = [],
        public ?int $metacriticScore = null,
        public ?string $metacriticUrl = null,
        public ?SteamPriceData $price = null,
        public array $platforms = [],
        public array $requirements = [],
        public array $dlcIds = [],
        public ?int $achievementCount = null,
        public ?int $recommendationCount = null,
        public ?string $website = null,
        public ?string $supportUrl = null,
        public string $storeUrl = '',
    ) {}

    /**
     * Create from Steam API response array.
     *
     * Thin facade kept for backwards compatibility; delegates to
     * {@see SteamGameDataFactory::make()}.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data, int $appId): self
    {
        return (new SteamGameDataFactory)->make($data, $appId);
    }

    /**
     * Convert to array format compatible with existing GamesInfo model.
     *
     * @return array<string, mixed>
     */
    public function toGamesInfoArray(): array
    {
        return [
            'title' => $this->title,
            'asin' => (string) $this->steamId,
            'url' => $this->storeUrl,
            'publisher' => $this->publisher ?? 'Unknown',
            'releasedate' => $this->releaseDate,
            'review' => $this->description ?? 'No description available',
            'cover' => ! empty($this->coverUrl) ? 1 : 0,
            'backdrop' => ! empty($this->backdropUrl) ? 1 : 0,
            'trailer' => $this->trailerUrl ?? '',
            'classused' => 'Steam',
            'esrb' => $this->metacriticScore !== null ? (string) $this->metacriticScore : 'Not Rated',
            'coverurl' => $this->coverUrl,
            'backdropurl' => $this->backdropUrl,
            'genres' => implode(',', $this->genres),
        ];
    }

    /**
     * Check if this is a game (not DLC, video, etc.).
     */
    public function isGame(): bool
    {
        return in_array($this->type, ['game', 'demo'], true);
    }

    /**
     * Check if game is free.
     */
    public function isFree(): bool
    {
        return $this->price !== null && $this->price->final <= 0;
    }

    /**
     * Get primary genre.
     */
    public function getPrimaryGenre(): ?string
    {
        return $this->genres[0] ?? null;
    }

    /**
     * Check if game supports a platform.
     */
    public function supportsPlatform(string $platform): bool
    {
        return in_array($platform, $this->platforms, true);
    }

    /**
     * Check if game has multiplayer.
     */
    public function hasMultiplayer(): bool
    {
        $multiplayerCategories = ['Multi-player', 'Online Multi-Player', 'Online Co-op', 'Local Multi-Player', 'Local Co-op'];

        return ! empty(array_intersect($multiplayerCategories, $this->categories));
    }

    /**
     * Check if game supports Steam Workshop.
     */
    public function hasSteamWorkshop(): bool
    {
        return in_array('Steam Workshop', $this->categories, true);
    }

    /**
     * Get formatted genres string.
     */
    public function getGenresString(): string
    {
        return implode(', ', $this->genres);
    }
}
