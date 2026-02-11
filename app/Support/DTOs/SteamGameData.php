<?php

declare(strict_types=1);

namespace App\Support\DTOs;

/**
 * Data Transfer Object for Steam Game data.
 *
 * Provides a type-safe, immutable representation of game data
 * retrieved from the Steam API.
 */
final readonly class SteamGameData
{
    /**
     * @param  int  $steamId  Steam App ID
     * @param  string  $title  Game title
     * @param  string  $type  App type (game, dlc, demo, etc.)
     * @param  string|null  $description  Short description
     * @param  string|null  $detailedDescription  Full description with HTML
     * @param  string|null  $about  About the game text
     * @param  string|null  $coverUrl  Header image URL
     * @param  string|null  $backdropUrl  Background image URL
     * @param  array<int, array{thumbnail: ?string, full: ?string}>  $screenshots  Screenshot URLs
     * @param  array<int, array{id: ?int, name: ?string, thumbnail: ?string, webm: ?string, mp4: ?string}>  $movies  Movie/trailer data
     * @param  string|null  $trailerUrl  Primary trailer URL
     * @param  string|null  $publisher  Publisher name(s)
     * @param  array<string>  $developers  Developer names
     * @param  string|null  $releaseDate  Release date (Y-m-d format)
     * @param  array<string>  $genres  Genre names
     * @param  array<string>  $categories  Category names (multiplayer, etc.)
     * @param  int|null  $metacriticScore  Metacritic score (0-100)
     * @param  string|null  $metacriticUrl  Metacritic URL
     * @param  SteamPriceData|null  $price  Price information
     * @param  array<string>  $platforms  Supported platforms
     * @param  array<string, array{minimum: ?string, recommended: ?string}>  $requirements  System requirements
     * @param  array<int>  $dlcIds  DLC App IDs
     * @param  int|null  $achievementCount  Total achievements
     * @param  int|null  $recommendationCount  Total recommendations
     * @param  string|null  $website  Official website URL
     * @param  string|null  $supportUrl  Support URL
     * @param  string  $storeUrl  Steam store URL
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
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data, int $appId): self
    {
        // Parse screenshots
        $screenshots = [];
        if (! empty($data['screenshots'])) {
            foreach ($data['screenshots'] as $ss) {
                $screenshots[] = [
                    'thumbnail' => $ss['path_thumbnail'] ?? null,
                    'full' => $ss['path_full'] ?? null,
                ];
            }
        }

        // Parse movies
        $movies = [];
        $trailerUrl = null;
        if (! empty($data['movies'])) {
            foreach ($data['movies'] as $movie) {
                $mp4Url = $movie['mp4']['max'] ?? ($movie['mp4']['480'] ?? null);
                $movies[] = [
                    'id' => $movie['id'] ?? null,
                    'name' => $movie['name'] ?? null,
                    'thumbnail' => $movie['thumbnail'] ?? null,
                    'webm' => $movie['webm']['max'] ?? ($movie['webm']['480'] ?? null),
                    'mp4' => $mp4Url,
                ];
                if ($trailerUrl === null && ! empty($mp4Url)) {
                    $trailerUrl = $mp4Url;
                }
            }
        }

        // Parse genres
        $genres = [];
        if (! empty($data['genres'])) {
            foreach ($data['genres'] as $genre) {
                if (! empty($genre['description'])) {
                    $genres[] = $genre['description'];
                }
            }
        }

        // Parse categories
        $categories = [];
        if (! empty($data['categories'])) {
            foreach ($data['categories'] as $cat) {
                if (! empty($cat['description'])) {
                    $categories[] = $cat['description'];
                }
            }
        }

        // Parse platforms
        $platforms = [];
        if (! empty($data['platforms'])) {
            if ($data['platforms']['windows'] ?? false) {
                $platforms[] = 'Windows';
            }
            if ($data['platforms']['mac'] ?? false) {
                $platforms[] = 'Mac';
            }
            if ($data['platforms']['linux'] ?? false) {
                $platforms[] = 'Linux';
            }
        }

        // Parse requirements
        $requirements = [];
        if (! empty($data['pc_requirements']) && ! is_array($data['pc_requirements']) === false) {
            if (is_array($data['pc_requirements'])) {
                $requirements['pc'] = [
                    'minimum' => $data['pc_requirements']['minimum'] ?? null,
                    'recommended' => $data['pc_requirements']['recommended'] ?? null,
                ];
            }
        }
        if (! empty($data['mac_requirements']) && is_array($data['mac_requirements'])) {
            $requirements['mac'] = [
                'minimum' => $data['mac_requirements']['minimum'] ?? null,
                'recommended' => $data['mac_requirements']['recommended'] ?? null,
            ];
        }
        if (! empty($data['linux_requirements']) && is_array($data['linux_requirements'])) {
            $requirements['linux'] = [
                'minimum' => $data['linux_requirements']['minimum'] ?? null,
                'recommended' => $data['linux_requirements']['recommended'] ?? null,
            ];
        }

        // Parse price
        $price = null;
        if (isset($data['price_overview'])) {
            $price = SteamPriceData::fromApiResponse($data['price_overview']);
        } elseif ($data['is_free'] ?? false) {
            $price = SteamPriceData::free();
        }

        // Parse release date
        $releaseDate = null;
        if (! empty($data['release_date']['date'])) {
            try {
                $releaseDate = \Illuminate\Support\Carbon::parse($data['release_date']['date'])->format('Y-m-d');
            } catch (\Exception $e) {
                $releaseDate = $data['release_date']['date'];
            }
        }

        // Parse publisher
        $publisher = null;
        if (! empty($data['publishers'])) {
            $publisher = implode(', ', array_filter(array_map('strval', $data['publishers'])));
        }

        // Parse developers
        $developers = [];
        if (! empty($data['developers'])) {
            $developers = array_values(array_filter(array_map('strval', $data['developers'])));
        }

        return new self(
            steamId: $appId,
            title: $data['name'] ?? '',
            type: $data['type'] ?? 'game',
            description: $data['short_description'] ?? null,
            detailedDescription: $data['detailed_description'] ?? null,
            about: $data['about_the_game'] ?? null,
            coverUrl: $data['header_image'] ?? null,
            backdropUrl: $data['background'] ?? ($data['background_raw'] ?? null),
            screenshots: $screenshots,
            movies: $movies,
            trailerUrl: $trailerUrl,
            publisher: $publisher,
            developers: $developers,
            releaseDate: $releaseDate,
            genres: $genres,
            categories: $categories,
            metacriticScore: $data['metacritic']['score'] ?? null,
            metacriticUrl: $data['metacritic']['url'] ?? null,
            price: $price,
            platforms: $platforms,
            requirements: $requirements,
            dlcIds: $data['dlc'] ?? [],
            achievementCount: $data['achievements']['total'] ?? null,
            recommendationCount: $data['recommendations']['total'] ?? null,
            website: $data['website'] ?? null,
            supportUrl: $data['support_info']['url'] ?? null,
            storeUrl: 'https://store.steampowered.com/app/'.$appId,
        );
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
