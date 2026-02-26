<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Custom iTunes Search API Service.
 * Replaces the DariusIII/php-itunes-api package with a direct implementation.
 * Supports music and ebook searches only (movies are not supported due to API issues).
 *
 * @see https://developer.apple.com/library/archive/documentation/AudioVideo/Conceptual/iTuneSearchAPI/index.html
 */
class ItunesService
{
    protected const API_URL = 'https://itunes.apple.com/search';

    protected const LOOKUP_URL = 'https://itunes.apple.com/lookup';

    protected Client $client;

    protected int $limit = 25;

    protected string $country = 'US';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'NNTmux/1.0',
            ],
        ]);
    }

    /**
     * Set the result limit.
     */
    public function limit(int $limit): self
    {
        $this->limit = min($limit, 200); // iTunes max is 200

        return $this;
    }

    /**
     * Set the country code for the search.
     */
    public function country(string $country): self
    {
        $this->country = strtoupper($country);

        return $this;
    }

    /**
     * Search for music albums.
     *
     * @return array<string, mixed>
     */
    public function searchAlbums(string $term): ?array
    {
        return $this->search($term, 'album', 'music');
    }

    /**
     * Search for music tracks.
     *
     * @return array<string, mixed>
     */
    public function searchTracks(string $term): ?array
    {
        return $this->search($term, 'song', 'music');
    }

    /**
     * Search for artists.
     *
     * @return array<string, mixed>
     */
    public function searchArtists(string $term): ?array
    {
        return $this->search($term, 'musicArtist', 'music');
    }

    /**
     * Search for ebooks.
     *
     * @return array<string, mixed>
     */
    public function searchEbooks(string $term): ?array
    {
        return $this->search($term, 'ebook', 'ebook');
    }

    /**
     * Search for audiobooks.
     *
     * @return array<string, mixed>
     */
    public function searchAudiobooks(string $term): ?array
    {
        return $this->search($term, 'audiobook', 'audiobook');
    }

    /**
     * Lookup by iTunes ID.
     *
     * @return array<string, mixed>
     */
    public function lookupById(int $id): ?array
    {
        $cacheKey = 'itunes_lookup_'.$id;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->client->get(self::LOOKUP_URL, [
                'query' => [
                    'id' => $id,
                    'country' => $this->country,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                Log::warning("iTunes API lookup returned status {$statusCode}");

                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['results']) || empty($data['results'])) {
                return null;
            }

            Cache::put($cacheKey, $data['results'][0], now()->addDays(7));

            return $data['results'][0];
        } catch (GuzzleException $e) {
            Log::error('iTunes API lookup error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Lookup artist by ID.
     *
     * @return array<string, mixed>
     */
    public function lookupArtist(int $artistId): ?array
    {
        return $this->lookupById($artistId);
    }

    /**
     * Get the first album result for a search term.
     *
     * @return array<string, mixed>|null Normalized album data or null
     */
    public function findAlbum(string $term): ?array
    {
        $results = $this->searchAlbums($term);

        if (empty($results)) {
            return null;
        }

        return $this->normalizeAlbumResult($results[0]); // @phpstan-ignore offsetAccess.notFound
    }

    /**
     * Get the first track result for a search term.
     *
     * @return array<string, mixed>|null Normalized track data or null
     */
    public function findTrack(string $term): ?array
    {
        $results = $this->searchTracks($term);

        if (empty($results)) {
            return null;
        }

        return $this->normalizeTrackResult($results[0]); // @phpstan-ignore offsetAccess.notFound
    }

    /**
     * Get the first ebook result for a search term.
     *
     * @return array<string, mixed>|null Normalized ebook data or null
     */
    public function findEbook(string $term): ?array
    {
        $results = $this->searchEbooks($term);

        if (empty($results)) {
            return null;
        }

        return $this->normalizeEbookResult($results[0]); // @phpstan-ignore offsetAccess.notFound
    }

    /**
     * Perform a search request to iTunes API.
     *
     * @return array<string, mixed>
     */
    protected function search(string $term, string $entity, string $media): ?array
    {
        $term = trim($term);
        if (empty($term)) {
            return null;
        }

        $cacheKey = 'itunes_search_'.md5($term.$entity.$media.$this->country.$this->limit);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->client->get(self::API_URL, [
                'query' => [
                    'term' => $term,
                    'entity' => $entity,
                    'media' => $media,
                    'country' => $this->country,
                    'limit' => $this->limit,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                Log::warning("iTunes API search returned status {$statusCode}");

                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['results'])) {
                return null;
            }

            $results = $data['results'];
            Cache::put($cacheKey, $results, now()->addHours(6));

            return $results;
        } catch (GuzzleException $e) {
            Log::error('iTunes API search error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Normalize album result to consistent format.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function normalizeAlbumResult(array $result): array
    {
        return [
            'id' => $result['collectionId'] ?? null,
            'name' => $result['collectionName'] ?? '',
            'artist' => $result['artistName'] ?? '',
            'artist_id' => $result['artistId'] ?? null,
            'genre' => $result['primaryGenreName'] ?? '',
            'release_date' => isset($result['releaseDate']) ? $this->parseDate($result['releaseDate']) : null,
            'track_count' => $result['trackCount'] ?? 0,
            'cover' => $this->getHighResCover($result['artworkUrl100'] ?? ''),
            'store_url' => $result['collectionViewUrl'] ?? '',
            'price' => $result['collectionPrice'] ?? null,
            'currency' => $result['currency'] ?? 'USD',
            'copyright' => $result['copyright'] ?? '',
            'explicit' => ($result['collectionExplicitness'] ?? '') === 'explicit',
            'raw' => $result,
        ];
    }

    /**
     * Normalize track result to consistent format.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function normalizeTrackResult(array $result): array
    {
        return [
            'id' => $result['trackId'] ?? null,
            'name' => $result['trackName'] ?? '',
            'artist' => $result['artistName'] ?? '',
            'artist_id' => $result['artistId'] ?? null,
            'album' => $result['collectionName'] ?? '',
            'album_id' => $result['collectionId'] ?? null,
            'genre' => $result['primaryGenreName'] ?? '',
            'release_date' => isset($result['releaseDate']) ? $this->parseDate($result['releaseDate']) : null,
            'duration_ms' => $result['trackTimeMillis'] ?? 0,
            'cover' => $this->getHighResCover($result['artworkUrl100'] ?? ''),
            'preview_url' => $result['previewUrl'] ?? '',
            'store_url' => $result['trackViewUrl'] ?? '',
            'price' => $result['trackPrice'] ?? null,
            'currency' => $result['currency'] ?? 'USD',
            'explicit' => ($result['trackExplicitness'] ?? '') === 'explicit',
            'raw' => $result,
        ];
    }

    /**
     * Normalize ebook result to consistent format.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function normalizeEbookResult(array $result): array
    {
        return [
            'id' => $result['trackId'] ?? null,
            'name' => $result['trackName'] ?? '',
            'author' => $result['artistName'] ?? '',
            'author_id' => $result['artistId'] ?? null,
            'genres' => $result['genres'] ?? [],
            'genre' => $result['primaryGenreName'] ?? ($result['genres'][0] ?? ''),
            'release_date' => isset($result['releaseDate']) ? $this->parseDate($result['releaseDate']) : null,
            'description' => $result['description'] ?? '',
            'cover' => $this->getHighResCover($result['artworkUrl100'] ?? ''),
            'store_url' => $result['trackViewUrl'] ?? '',
            'price' => $result['price'] ?? null,
            'currency' => $result['currency'] ?? 'USD',
            'average_rating' => $result['averageUserRating'] ?? null,
            'rating_count' => $result['userRatingCount'] ?? 0,
            'raw' => $result,
        ];
    }

    /**
     * Convert iTunes 100x100 artwork URL to higher resolution.
     */
    protected function getHighResCover(string $url, int $size = 800): string
    {
        if (empty($url)) {
            return '';
        }

        // iTunes artwork URLs typically end with /100x100bb.jpg
        // We can replace it with larger sizes up to 3000x3000
        return preg_replace('/\d+x\d+(bb)?\./', $size.'x'.$size.'bb.', $url) ?? $url;
    }

    /**
     * Parse ISO 8601 date string.
     */
    protected function parseDate(string $dateString): ?string
    {
        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
