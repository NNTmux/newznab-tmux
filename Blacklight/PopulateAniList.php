<?php

namespace Blacklight;

use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;

class PopulateAniList
{
    /**
     * AniList GraphQL API endpoint
     */
    private const API_URL = 'https://graphql.anilist.co';

    /**
     * Rate limit: 90 requests per minute
     */
    private const RATE_LIMIT_PER_MINUTE = 90;

    /**
     * Whether to echo message output.
     */
    public bool $echooutput;

    /**
     * The directory to store anime covers.
     */
    public string $imgSavePath;

    /**
     * HTTP client for API requests
     */
    protected Client $client;

    /**
     * Rate limiting: track requests and timestamps
     */
    private array $rateLimitQueue = [];

    protected ColorCLI $colorCli;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');
        $this->colorCli = new ColorCLI;

        // Use storage_path directly to match CoverController expectations
        $this->imgSavePath = storage_path('covers/anime/');
        $this->client = new Client([
            'base_uri' => self::API_URL,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Main switch that initiates AniList table population.
     *
     * @throws \Exception
     */
    public function populateTable(string $type = '', int|string $anilistId = ''): void
    {
        switch ($type) {
            case 'info':
                $this->populateInfoTable($anilistId);
                break;
        }
    }

    /**
     * Search for anime by title using AniList GraphQL API.
     *
     * @return array|false
     *
     * @throws \Exception
     */
    public function searchAnime(string $title, int $limit = 10)
    {
        $query = '
            query ($search: String, $perPage: Int) {
                Page (perPage: $perPage) {
                    media (search: $search, type: ANIME) {
                        id
                        idMal
                        title {
                            romaji
                            english
                            native
                        }
                        type
                        format
                        status
                        countryOfOrigin
                        episodes
                        duration
                        source
                        popularity
                        favourites
                        startDate {
                            year
                            month
                            day
                        }
                        endDate {
                            year
                            month
                            day
                        }
                        description
                        averageScore
                        hashtag
                        coverImage {
                            large
                        }
                        genres
                        studios {
                            nodes {
                                name
                            }
                        }
                        characters {
                            nodes {
                                name {
                                    full
                                }
                            }
                        }
                        relations {
                            edges {
                                relationType
                                node {
                                    id
                                    title {
                                        romaji
                                        english
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ';

        $variables = [
            'search' => $title,
            'perPage' => $limit,
        ];

        $response = $this->makeGraphQLRequest($query, $variables);

        if ($response === false || ! isset($response['data']['Page']['media'])) {
            return false;
        }

        return $response['data']['Page']['media'];
    }

    /**
     * Get anime by AniList ID.
     *
     * @return array|false
     *
     * @throws \Exception
     */
    public function getAnimeById(int $anilistId)
    {
        $query = '
            query ($id: Int) {
                Media (id: $id, type: ANIME) {
                    id
                    idMal
                    title {
                        romaji
                        english
                        native
                    }
                    type
                    format
                    status
                    countryOfOrigin
                    episodes
                    duration
                    source
                    popularity
                    favourites
                    startDate {
                        year
                        month
                        day
                    }
                    endDate {
                        year
                        month
                        day
                    }
                    description
                    averageScore
                    hashtag
                    coverImage {
                        large
                    }
                    genres
                    studios {
                        nodes {
                            name
                        }
                    }
                    characters {
                        nodes {
                            name {
                                full
                            }
                        }
                    }
                    relations {
                        edges {
                            relationType
                            node {
                                id
                                title {
                                    romaji
                                    english
                                }
                            }
                        }
                    }
                }
            }
        ';

        $variables = ['id' => $anilistId];

        $response = $this->makeGraphQLRequest($query, $variables);

        if ($response === false || ! isset($response['data']['Media'])) {
            return false;
        }

        return $response['data']['Media'];
    }

    /**
     * Make a GraphQL request to AniList API with rate limiting.
     *
     * @return array|false
     *
     * @throws \Exception
     */
    private function makeGraphQLRequest(string $query, array $variables = [])
    {
        // Enforce rate limiting
        $this->enforceRateLimit();

        try {
            $response = $this->client->post('', [
                'json' => [
                    'query' => $query,
                    'variables' => $variables,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            // Track rate limit from headers
            $remaining = (int) ($response->getHeader('X-RateLimit-Remaining')[0] ?? self::RATE_LIMIT_PER_MINUTE);
            $resetAt = (int) ($response->getHeader('X-RateLimit-Reset')[0] ?? time() + 60);

            // Record this request
            $this->rateLimitQueue[] = [
                'timestamp' => time(),
                'remaining' => $remaining,
                'resetAt' => $resetAt,
            ];

            // Clean old entries (older than 1 minute)
            $this->rateLimitQueue = array_filter($this->rateLimitQueue, function ($entry) {
                return $entry['timestamp'] > (time() - 60);
            });

            if ($statusCode === 200 && isset($body['data'])) {
                return $body;
            }

            if (isset($body['errors'])) {
                if ($this->echooutput) {
                    $this->colorCli->error('AniList API Error: '.json_encode($body['errors']));
                }

                return false;
            }

            return false;
        } catch (GuzzleException $e) {
            if ($this->echooutput) {
                $this->colorCli->error('AniList API Request Failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Enforce rate limiting: 90 requests per minute.
     *
     * @throws \Exception
     */
    private function enforceRateLimit(): void
    {
        // Count requests in the last minute
        $recentRequests = array_filter($this->rateLimitQueue, function ($entry) {
            return $entry['timestamp'] > (time() - 60);
        });

        $requestCount = count($recentRequests);

        // If we're approaching the limit, wait
        if ($requestCount >= (self::RATE_LIMIT_PER_MINUTE - 5)) {
            // Wait until the oldest request is more than 1 minute old
            if (! empty($this->rateLimitQueue)) {
                $oldestRequest = min(array_column($this->rateLimitQueue, 'timestamp'));
                $waitTime = 60 - (time() - $oldestRequest) + 1;

                if ($waitTime > 0 && $waitTime <= 60) {
                    if ($this->echooutput) {
                        $this->colorCli->warning("Rate limit approaching. Waiting {$waitTime} seconds...");
                    }
                    sleep($waitTime);
                }
            }
        }
    }

    /**
     * Insert or update AniList title data.
     */
    private function insertAniListTitle(int $anidbid, string $type, string $lang, string $title): void
    {
        $check = AnidbTitle::query()->where([
            'anidbid' => $anidbid,
            'type' => $type,
            'lang' => $lang,
            'title' => $title,
        ])->first();

        if ($check === null) {
            AnidbTitle::insertOrIgnore([
                'anidbid' => $anidbid,
                'type' => $type,
                'lang' => $lang,
                'title' => $title,
            ]);
        }
    }

    /**
     * Insert or update AniList info data.
     */
    private function insertAniListInfo(int $anidbid, array $anilistData): void
    {
        // Extract data from AniList response
        $startDate = null;
        if (isset($anilistData['startDate']['year'], $anilistData['startDate']['month'], $anilistData['startDate']['day'])) {
            $startDate = sprintf(
                '%04d-%02d-%02d',
                $anilistData['startDate']['year'],
                $anilistData['startDate']['month'] ?? 1,
                $anilistData['startDate']['day'] ?? 1
            );
        }

        $endDate = null;
        if (isset($anilistData['endDate']['year'], $anilistData['endDate']['month'], $anilistData['endDate']['day'])) {
            $endDate = sprintf(
                '%04d-%02d-%02d',
                $anilistData['endDate']['year'],
                $anilistData['endDate']['month'] ?? 1,
                $anilistData['endDate']['day'] ?? 1
            );
        }

        $description = $anilistData['description'] ?? '';
        // Remove HTML tags from description
        $description = strip_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $rating = isset($anilistData['averageScore']) ? (string) $anilistData['averageScore'] : null;

        $picture = null;
        if (isset($anilistData['coverImage']['large'])) {
            $picture = basename(parse_url($anilistData['coverImage']['large'], PHP_URL_PATH));
        }

        $genres = '';
        if (isset($anilistData['genres']) && is_array($anilistData['genres'])) {
            $genres = implode(', ', $anilistData['genres']);
        }

        $creators = '';
        if (isset($anilistData['studios']['nodes']) && is_array($anilistData['studios']['nodes'])) {
            $studioNames = array_map(function ($studio) {
                return $studio['name'] ?? '';
            }, $anilistData['studios']['nodes']);
            $creators = implode(', ', array_filter($studioNames));
        }

        $characters = '';
        if (isset($anilistData['characters']['nodes']) && is_array($anilistData['characters']['nodes'])) {
            $characterNames = array_map(function ($character) {
                return $character['name']['full'] ?? '';
            }, array_slice($anilistData['characters']['nodes'], 0, 10)); // Limit to 10 characters
            $characters = implode(', ', array_filter($characterNames));
        }

        $related = '';
        $similar = '';
        if (isset($anilistData['relations']['edges']) && is_array($anilistData['relations']['edges'])) {
            $relatedItems = [];
            $similarItems = [];
            foreach ($anilistData['relations']['edges'] as $edge) {
                $relationType = $edge['relationType'] ?? '';
                $title = $edge['node']['title']['english'] ?? $edge['node']['title']['romaji'] ?? '';
                $id = $edge['node']['id'] ?? '';

                if (in_array($relationType, ['SEQUEL', 'PREQUEL', 'SIDE_STORY', 'PARENT', 'SPIN_OFF'])) {
                    $relatedItems[] = $title.' ('.$id.')';
                } elseif (in_array($relationType, ['ALTERNATIVE', 'CHARACTER'])) {
                    $similarItems[] = $title.' ('.$id.')';
                }
            }
            $related = implode(', ', array_slice($relatedItems, 0, 20));
            $similar = implode(', ', array_slice($similarItems, 0, 20));
        }

        $anilistId = $anilistData['id'] ?? null;
        $malId = $anilistData['idMal'] ?? null;
        $country = $anilistData['countryOfOrigin'] ?? null;
        $mediaType = $anilistData['type'] ?? null; // ANIME or MANGA
        $episodes = isset($anilistData['episodes']) ? (int) $anilistData['episodes'] : null;
        $duration = isset($anilistData['duration']) ? (int) $anilistData['duration'] : null;
        $status = $anilistData['status'] ?? null;
        $source = $anilistData['source'] ?? null;
        $hashtag = $anilistData['hashtag'] ?? null;

        $check = AnidbInfo::query()->where('anidbid', $anidbid)->first();

        if ($check === null) {
            AnidbInfo::insert([
                'anidbid' => $anidbid,
                'anilist_id' => $anilistId,
                'mal_id' => $malId,
                'country' => $country,
                'media_type' => $mediaType,
                'type' => $anilistData['format'] ?? null,
                'episodes' => $episodes,
                'duration' => $duration,
                'status' => $status,
                'source' => $source,
                'hashtag' => $hashtag,
                'startdate' => $startDate,
                'enddate' => $endDate,
                'description' => $description,
                'rating' => $rating,
                'picture' => $picture,
                'categories' => $genres,
                'characters' => $characters,
                'creators' => $creators,
                'related' => $related,
                'similar' => $similar,
                'updated' => now(),
            ]);
        } else {
            AnidbInfo::query()
                ->where('anidbid', $anidbid)
                ->update([
                    'anilist_id' => $anilistId ?? $check->anilist_id,
                    'mal_id' => $malId ?? $check->mal_id,
                    'country' => $country ?? $check->country,
                    'media_type' => $mediaType ?? $check->media_type,
                    'type' => $anilistData['format'] ?? $anilistData['type'] ?? $check->type,
                    'episodes' => $episodes ?? $check->episodes,
                    'duration' => $duration ?? $check->duration,
                    'status' => $status ?? $check->status,
                    'source' => $source ?? $check->source,
                    'hashtag' => $hashtag ?? $check->hashtag,
                    'startdate' => $startDate ?? $check->startdate,
                    'enddate' => $endDate ?? $check->enddate,
                    'description' => $description ?: $check->description,
                    'rating' => $rating ?? $check->rating,
                    'picture' => $picture ?? $check->picture,
                    'categories' => $genres ?: $check->categories,
                    'characters' => $characters ?: $check->characters,
                    'creators' => $creators ?: $check->creators,
                    'related' => $related ?: $check->related,
                    'similar' => $similar ?: $check->similar,
                    'updated' => now(),
                ]);
        }

        // Insert titles
        if (isset($anilistData['title']['romaji']) && ! empty($anilistData['title']['romaji'])) {
            $this->insertAniListTitle($anidbid, 'main', 'x-jat', $anilistData['title']['romaji']);
        }
        if (isset($anilistData['title']['english']) && ! empty($anilistData['title']['english'])) {
            $this->insertAniListTitle($anidbid, 'official', 'en', $anilistData['title']['english']);
        }
        if (isset($anilistData['title']['native']) && ! empty($anilistData['title']['native'])) {
            $this->insertAniListTitle($anidbid, 'main', 'ja', $anilistData['title']['native']);
        }

        // Download cover image if available
        if (! empty($picture) && isset($anilistData['coverImage']['large'])) {
            $this->downloadCoverImage($anidbid, $anilistData['coverImage']['large']);
        }
    }

    /**
     * Download cover image from AniList.
     */
    private function downloadCoverImage(int $anidbid, string $imageUrl): void
    {
        // Use the format expected by getReleaseCover: {id}-cover.jpg
        // This matches the format used by movies: {id}-cover.jpg
        $coverFilename = $anidbid.'-cover.jpg';
        $coverPath = $this->imgSavePath.$coverFilename;
        
        if (file_exists($coverPath)) {
            return; // Already exists
        }

        try {
            // Ensure directory exists with proper permissions
            if (! is_dir($this->imgSavePath)) {
                if (! mkdir($this->imgSavePath, 0755, true) && ! is_dir($this->imgSavePath)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->imgSavePath));
                }
            }

            $response = $this->client->get($imageUrl);
            $imageData = $response->getBody()->getContents();

            // Write the image file
            $bytesWritten = file_put_contents($coverPath, $imageData);
            
            if ($bytesWritten === false) {
                throw new \RuntimeException("Failed to write cover image to {$coverPath}");
            }
            
            // Set proper file permissions
            chmod($coverPath, 0644);
            
            if ($this->echooutput) {
                $this->colorCli->info("Downloaded cover image for ID {$anidbid} from AniList to {$coverPath}");
            }
        } catch (GuzzleException $e) {
            if ($this->echooutput) {
                $this->colorCli->warning("Failed to download cover image for ID {$anidbid}: ".$e->getMessage());
            }
        } catch (\Exception $e) {
            if ($this->echooutput) {
                $this->colorCli->error("Error saving cover image for ID {$anidbid}: ".$e->getMessage());
            }
        }
    }

    /**
     * Directs flow for populating the AniList Info table.
     *
     * @throws \Exception
     */
    private function populateInfoTable(string $anilistId = ''): void
    {
        if (empty($anilistId)) {
            // Get titles that need updating (missing anilist_id or stale)
            $anidbIds = AnidbTitle::query()
                ->selectRaw('DISTINCT anidb_titles.anidbid')
                ->leftJoin('anidb_info as ai', 'ai.anidbid', '=', 'anidb_titles.anidbid')
                ->where(function ($query) {
                    $query->whereNull('ai.anilist_id')
                        ->orWhere('ai.updated', '<', now()->subWeek());
                })
                ->limit(100) // Process in batches
                ->get();

            foreach ($anidbIds as $anidb) {
                // Try to find by title first
                $title = AnidbTitle::query()
                    ->where('anidbid', $anidb->anidbid)
                    ->where('lang', 'en')
                    ->value('title');

                if ($title) {
                    $searchResults = $this->searchAnime($title, 1);
                    if ($searchResults && ! empty($searchResults)) {
                        $anilistData = $searchResults[0];
                        $this->insertAniListInfo($anidb->anidbid, $anilistData);
                        // Rate limiting is handled in makeGraphQLRequest
                    }
                }
            }
        } else {
            // Get specific anime by AniList ID
            $anilistData = $this->getAnimeById((int) $anilistId);

            if ($anilistData === false) {
                if ($this->echooutput) {
                    $this->colorCli->info("Anime with AniList ID: {$anilistId} not found.");
                }

                return;
            }

            // We need to find or create an anidbid for this
            // For now, use anilist_id as anidbid if not found
            $anidbid = AnidbInfo::query()
                ->where('anilist_id', $anilistId)
                ->value('anidbid');

            if (! $anidbid) {
                // Create a new entry using anilist_id as anidbid
                $anidbid = (int) $anilistId;
            }

            $this->insertAniListInfo($anidbid, $anilistData);
        }
    }
}


