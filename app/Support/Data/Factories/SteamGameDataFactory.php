<?php

declare(strict_types=1);

namespace App\Support\Data\Factories;

use App\Support\Data\SteamGameData;
use App\Support\Data\SteamPriceData;
use Illuminate\Support\Carbon;

/**
 * Factory for building {@see SteamGameData} from a raw Steam Store API payload.
 *
 * Extracted from the previous static `SteamGameData::fromApiResponse` so the
 * Data object stays a pure structure and reshaping logic lives separately.
 */
final class SteamGameDataFactory
{
    /**
     * Build a SteamGameData from a raw Steam API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public function make(array $data, int $appId): SteamGameData
    {
        return new SteamGameData(
            steamId: $appId,
            title: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? 'game'),
            description: $data['short_description'] ?? null,
            detailedDescription: $data['detailed_description'] ?? null,
            about: $data['about_the_game'] ?? null,
            coverUrl: $data['header_image'] ?? null,
            backdropUrl: $data['background'] ?? ($data['background_raw'] ?? null),
            screenshots: $this->parseScreenshots($data),
            movies: $this->parseMovies($data, $trailerUrl),
            trailerUrl: $trailerUrl,
            publisher: $this->parsePublisher($data),
            developers: $this->parseDevelopers($data),
            releaseDate: $this->parseReleaseDate($data),
            genres: $this->parseGenres($data),
            categories: $this->parseCategories($data),
            metacriticScore: $data['metacritic']['score'] ?? null,
            metacriticUrl: $data['metacritic']['url'] ?? null,
            price: $this->parsePrice($data),
            platforms: $this->parsePlatforms($data),
            requirements: $this->parseRequirements($data),
            dlcIds: $data['dlc'] ?? [],
            achievementCount: $data['achievements']['total'] ?? null,
            recommendationCount: $data['recommendations']['total'] ?? null,
            website: $data['website'] ?? null,
            supportUrl: $data['support_info']['url'] ?? null,
            storeUrl: 'https://store.steampowered.com/app/'.$appId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{thumbnail: ?string, full: ?string}>
     */
    private function parseScreenshots(array $data): array
    {
        $screenshots = [];
        if (! empty($data['screenshots']) && is_array($data['screenshots'])) {
            foreach ($data['screenshots'] as $ss) {
                $screenshots[] = [
                    'thumbnail' => $ss['path_thumbnail'] ?? null,
                    'full' => $ss['path_full'] ?? null,
                ];
            }
        }

        return $screenshots;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: ?int, name: ?string, thumbnail: ?string, webm: ?string, mp4: ?string}>
     */
    private function parseMovies(array $data, ?string &$trailerUrl): array
    {
        $movies = [];
        $trailerUrl = null;
        if (! empty($data['movies']) && is_array($data['movies'])) {
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

        return $movies;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function parseGenres(array $data): array
    {
        $genres = [];
        if (! empty($data['genres']) && is_array($data['genres'])) {
            foreach ($data['genres'] as $genre) {
                if (! empty($genre['description'])) {
                    $genres[] = (string) $genre['description'];
                }
            }
        }

        return $genres;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function parseCategories(array $data): array
    {
        $categories = [];
        if (! empty($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $cat) {
                if (! empty($cat['description'])) {
                    $categories[] = (string) $cat['description'];
                }
            }
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function parsePlatforms(array $data): array
    {
        $platforms = [];
        if (! empty($data['platforms']) && is_array($data['platforms'])) {
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

        return $platforms;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{minimum: ?string, recommended: ?string}>
     */
    private function parseRequirements(array $data): array
    {
        $requirements = [];
        foreach (['pc' => 'pc_requirements', 'mac' => 'mac_requirements', 'linux' => 'linux_requirements'] as $key => $field) {
            if (! empty($data[$field]) && is_array($data[$field])) {
                $requirements[$key] = [
                    'minimum' => $data[$field]['minimum'] ?? null,
                    'recommended' => $data[$field]['recommended'] ?? null,
                ];
            }
        }

        return $requirements;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parsePrice(array $data): ?SteamPriceData
    {
        if (isset($data['price_overview']) && is_array($data['price_overview'])) {
            return SteamPriceData::fromApiResponse($data['price_overview']);
        }

        if ($data['is_free'] ?? false) {
            return SteamPriceData::free();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseReleaseDate(array $data): ?string
    {
        if (empty($data['release_date']['date'])) {
            return null;
        }

        try {
            return Carbon::parse((string) $data['release_date']['date'])->format('Y-m-d');
        } catch (\Exception) {
            return (string) $data['release_date']['date'];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parsePublisher(array $data): ?string
    {
        if (empty($data['publishers']) || ! is_array($data['publishers'])) {
            return null;
        }

        return implode(', ', array_filter(array_map('strval', $data['publishers'])));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function parseDevelopers(array $data): array
    {
        if (empty($data['developers']) || ! is_array($data['developers'])) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $data['developers'])));
    }
}
