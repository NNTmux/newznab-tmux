<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use MarcReichel\IGDBLaravel\Models\Company;
use MarcReichel\IGDBLaravel\Models\Game;

/**
 * IGDBService - IGDB (Internet Game Database) API integration.
 *
 * Note: MarcReichel\IGDBLaravel\Models\Game and Company use dynamic properties
 * (name, id, etc.) that PHPStan cannot resolve.
 *
 * Features:
 * - Rate limiting and caching
 * - Multiple search strategies for better matching
 * - Complete game metadata retrieval
 * - Age rating and release date extraction
 */
class IGDBService
{
    // Rate limiting constants
    protected const string RATE_LIMIT_KEY = 'igdb_api_rate_limit';

    protected const int REQUESTS_PER_MINUTE = 4;

    protected const int DECAY_SECONDS = 60;

    // Cache TTLs
    protected const int GAME_CACHE_TTL = 86400; // 24 hours

    protected const int FAILED_LOOKUP_CACHE_TTL = 3600; // 1 hour

    // Matching configuration
    protected const int MATCH_THRESHOLD = 85;

    // PC Platform IDs in IGDB
    protected const array PC_PLATFORM_IDS = [6, 13, 14, 3]; // PC Windows, DOS, Mac, Linux

    /**
     * Check if IGDB is configured.
     */
    public function isConfigured(): bool
    {
        return config('config.credentials.client_id') !== ''
            && config('config.credentials.client_secret') !== '';
    }

    /**
     * Search for a game by title and return the best matching Game object.
     */
    public function search(string $title): ?Game
    {
        if (! $this->isConfigured() || empty($title)) {
            return null;
        }

        $cacheKey = 'igdb_search:'.md5(mb_strtolower($title));

        // Check failed lookup cache
        if (Cache::has("igdb_search_failed:{$cacheKey}")) {
            Log::debug('IGDBService: Skipping previously failed search', ['title' => $title]);

            return null;
        }

        // Check successful search cache
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('IGDBService: Using cached search result', ['title' => $title]);

            return $cached instanceof Game ? $cached : null;
        }

        $game = $this->searchWithStrategies($title);

        if ($game !== null) {
            Cache::put($cacheKey, $game, self::GAME_CACHE_TTL);
            Log::info('IGDBService: Found match', ['title' => $title, 'matched' => $game->name]); // @phpstan-ignore property.notFound
        } else {
            Cache::put("igdb_search_failed:{$cacheKey}", true, self::FAILED_LOOKUP_CACHE_TTL);
            Log::debug('IGDBService: No match found', ['title' => $title]);
        }

        return $game;
    }

    /**
     * Get complete game details from a Game object.
     *
     * @return array{
     *     title: string,
     *     asin: string,
     *     review: string,
     *     coverurl: string,
     *     releasedate: string,
     *     esrb: string,
     *     url: string,
     *     backdropurl: string,
     *     trailer: string,
     *     publisher: string,
     *     developer: string,
     *     genres: array,
     * }|false
     */
    public function getGameDetails(Game $game): array|false
    {
        if (empty($game->name)) {
            return false;
        }

        $genreName = '';

        return $this->buildGameData($game, $genreName);
    }

    /**
     * Search IGDB using multiple strategies for better match rates.
     */
    protected function searchWithStrategies(string $title): ?Game
    {
        // Strategy 1: Exact name search with PC platform filter
        $game = $this->searchExact($title);
        if ($game !== null) {
            Log::debug('IGDBService: Exact match found', ['title' => $title, 'matched' => $game->name]); // @phpstan-ignore property.notFound

            return $game;
        }

        // Strategy 2: Fuzzy search using IGDB's search endpoint
        $game = $this->searchFuzzy($title);
        if ($game !== null) {
            Log::debug('IGDBService: Fuzzy match found', ['title' => $title, 'matched' => $game->name]); // @phpstan-ignore property.notFound

            return $game;
        }

        // Strategy 3: Search without special characters
        $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        if ($cleanTitle !== $title && $cleanTitle !== '') {
            $game = $this->searchFuzzy($cleanTitle);
            if ($game !== null) {
                Log::debug('IGDBService: Clean title match found', ['title' => $title, 'matched' => $game->name]); // @phpstan-ignore property.notFound

                return $game;
            }
        }

        // Strategy 4: Try with common subtitle patterns removed
        $baseTitle = $this->extractBaseTitle($title);
        if ($baseTitle !== $title && $baseTitle !== $cleanTitle && $baseTitle !== '') {
            $game = $this->searchFuzzy($baseTitle);
            if ($game !== null) {
                Log::debug('IGDBService: Base title match found', ['title' => $title, 'matched' => $game->name]); // @phpstan-ignore property.notFound

                return $game;
            }
        }

        return null;
    }

    /**
     * Exact name search on IGDB with PC platform filter.
     */
    protected function searchExact(string $title): ?Game
    {
        try {
            $result = RateLimiter::attempt(
                self::RATE_LIMIT_KEY,
                self::REQUESTS_PER_MINUTE,
                function () use ($title) {
                    return Game::where('name', $title)
                        ->whereIn('platforms', self::PC_PLATFORM_IDS)
                        ->with($this->getGameRelations())
                        ->orderByDesc('aggregated_rating_count')
                        ->first();
                },
                self::DECAY_SECONDS
            );

            return $result instanceof Game ? $result : null;
        } catch (\Exception $e) {
            Log::warning('IGDBService: Exact search error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fuzzy search on IGDB using the search endpoint.
     */
    protected function searchFuzzy(string $title): ?Game
    {
        try {
            $results = RateLimiter::attempt(
                self::RATE_LIMIT_KEY,
                self::REQUESTS_PER_MINUTE,
                function () use ($title) {
                    return Game::search($title)
                        ->whereIn('platforms', self::PC_PLATFORM_IDS)
                        ->where('category', 0) // Main game only (not DLC, expansion, etc.)
                        ->with($this->getGameRelations())
                        ->orderByDesc('aggregated_rating_count')
                        ->limit(10)
                        ->get();
                },
                self::DECAY_SECONDS
            );

            if ($results === true || empty($results)) {
                return null;
            }

            return $this->findBestMatch($results, $title);
        } catch (\Exception $e) {
            Log::warning('IGDBService: Fuzzy search error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get relations to load with IGDB queries.
     */
    protected function getGameRelations(): array
    {
        return [
            'cover' => ['url', 'image_id'],
            'screenshots' => ['url', 'image_id'],
            'artworks' => ['url', 'image_id'],
            'videos' => ['video_id', 'name'],
            'involved_companies' => ['company', 'publisher', 'developer'],
            'genres' => ['name'],
            'themes' => ['name'],
            'game_modes' => ['name'],
            'player_perspectives' => ['name'],
            'age_ratings' => ['rating', 'category'],
            'websites' => ['url', 'category'],
            'platforms' => ['name', 'abbreviation'],
            'release_dates' => ['date', 'platform', 'human'],
        ];
    }

    /**
     * Find the best matching game from results.
     */
    protected function findBestMatch($results, string $title): ?Game
    {
        $bestMatch = null;
        $bestScore = 0;
        $normalizedQuery = $this->normalizeForMatch($title);

        foreach ($results as $game) {
            $normalizedName = $this->normalizeForMatch($game->name);
            $score = $this->computeSimilarity($normalizedQuery, $normalizedName);

            // Boost score for games with more ratings (more popular/verified)
            if (isset($game->aggregated_rating_count) && $game->aggregated_rating_count > 10) {
                $score += min(5, $game->aggregated_rating_count / 100);
            }

            // Boost score for games with covers (more complete data)
            if (isset($game->cover)) {
                $score += 2;
            }

            // Check alternative names if available
            if (isset($game->alternative_names)) {
                foreach ($game->alternative_names as $altName) {
                    $altScore = $this->computeSimilarity($normalizedQuery, $this->normalizeForMatch($altName['name'] ?? ''));
                    if ($altScore > $score) {
                        $score = $altScore;
                    }
                }
            }

            if ($score >= self::MATCH_THRESHOLD && $score > $bestScore) {
                $bestMatch = $game;
                $bestScore = $score;
            }
        }

        if ($bestMatch !== null) {
            Log::debug('IGDBService: Best match selected', [
                'query' => $title,
                'matched' => $bestMatch->name,
                'score' => $bestScore,
            ]);
        }

        return $bestMatch;
    }

    /**
     * Build game data array from IGDB Game model.
     */
    public function buildGameData(Game $game, string &$genreName): array
    {
        // Extract publishers and developers
        $publishers = [];
        $developers = [];
        if (! empty($game->involved_companies)) {
            $involvedCompanies = $game->involved_companies;
            if ($involvedCompanies instanceof \Illuminate\Support\Collection) {
                $involvedCompanies = $involvedCompanies->toArray();
            }
            foreach ($involvedCompanies as $company) {
                $isPublisher = is_array($company) ? ($company['publisher'] ?? false) : ($company->publisher ?? false);
                $isDeveloper = is_array($company) ? ($company['developer'] ?? false) : ($company->developer ?? false);
                $companyId = is_array($company) ? ($company['company'] ?? null) : ($company->company ?? null);

                if ($isPublisher === true && $companyId) {
                    $companyData = Company::find($companyId);
                    if ($companyData) {
                        $publishers[] = $companyData->name; // @phpstan-ignore property.notFound
                    }
                }
                if ($isDeveloper === true && $companyId) {
                    $companyData = Company::find($companyId);
                    if ($companyData) {
                        $developers[] = $companyData->name; // @phpstan-ignore property.notFound
                    }
                }
            }
        }

        // Extract genres
        $genres = $this->extractGenres($game);
        $genreName = $this->matchGenre(implode(',', array_filter($genres)));

        // Get cover and backdrop URLs
        $coverUrl = $this->getImageUrl($game->cover ?? null, 'cover_big');
        $backdropUrl = $this->getBackdropUrl($game);

        // Get trailer URL
        $trailerUrl = $this->getTrailerUrl($game);

        // Get rating
        $esrb = $this->getAgeRating($game);

        // Get release date for PC
        $releaseDate = $this->getReleaseDate($game);

        // Get game URL
        $gameUrl = $game->url ?? ('https://www.igdb.com/games/'.($game->slug ?? $game->id)); // @phpstan-ignore property.notFound

        // Build review text
        $review = $this->buildReview($game, $developers);

        Log::info('IGDBService: Game data built', [
            'title' => $game->name, // @phpstan-ignore property.notFound
            'id' => $game->id, // @phpstan-ignore property.notFound
            'has_cover' => ! empty($coverUrl),
            'has_backdrop' => ! empty($backdropUrl),
            'genres' => $genres,
        ]);

        return [
            'title' => $game->name, // @phpstan-ignore property.notFound
            'asin' => 'igdb-'.$game->id, // @phpstan-ignore property.notFound
            'review' => $review,
            'coverurl' => $coverUrl,
            'releasedate' => $releaseDate,
            'esrb' => $esrb,
            'url' => $gameUrl,
            'backdropurl' => $backdropUrl,
            'trailer' => $trailerUrl,
            'publisher' => ! empty($publishers) ? implode(', ', array_slice($publishers, 0, 3)) : 'Unknown',
            'developer' => ! empty($developers) ? implode(', ', array_slice($developers, 0, 3)) : '',
            'genres' => $genres,
        ];
    }

    /**
     * Extract genres from game data.
     */
    protected function extractGenres(Game $game): array
    {
        $genres = [];
        if (! empty($game->genres)) {
            $gameGenres = $game->genres;
            if ($gameGenres instanceof \Illuminate\Support\Collection) {
                $gameGenres = $gameGenres->toArray();
            }
            foreach ($gameGenres as $genre) {
                $genres[] = is_array($genre) ? ($genre['name'] ?? '') : ($genre->name ?? '');
            }
        }

        // Fall back to themes if no genres
        if (empty($genres) && ! empty($game->themes)) {
            $gameThemes = $game->themes;
            if ($gameThemes instanceof \Illuminate\Support\Collection) {
                $gameThemes = $gameThemes->toArray();
            }
            foreach ($gameThemes as $theme) {
                $genres[] = is_array($theme) ? ($theme['name'] ?? '') : ($theme->name ?? '');
            }
        }

        return array_filter($genres);
    }

    /**
     * Get properly formatted IGDB image URL.
     */
    public function getImageUrl(array|object|null $imageData, string $size = 'cover_big'): string
    {
        if (empty($imageData)) {
            return '';
        }

        if (is_object($imageData)) {
            $imageId = $imageData->image_id ?? ($imageData->imageId ?? null);
            $url = $imageData->url ?? null;
            $imageData = [
                'image_id' => $imageId,
                'url' => $url,
            ];
        }

        if (! empty($imageData['image_id'])) {
            return 'https://images.igdb.com/igdb/image/upload/t_'.$size.'/'.$imageData['image_id'].'.jpg';
        }

        if (! empty($imageData['url'])) {
            $url = $imageData['url'];
            if (strpos($url, '//') === 0) {
                $url = 'https:'.$url;
            }

            return preg_replace('/t_[a-z0-9_]+/', 't_'.$size, $url);
        }

        return '';
    }

    /**
     * Get backdrop URL from artworks or screenshots.
     */
    protected function getBackdropUrl(Game $game): string
    {
        if (! empty($game->artworks)) {
            $artworks = $game->artworks;
            $firstArtwork = ($artworks instanceof \Illuminate\Support\Collection) ? $artworks->first() : ($artworks[0] ?? null);
            $url = $this->getImageUrl($firstArtwork, '1080p');
            if (! empty($url)) {
                return $url;
            }
        }

        if (! empty($game->screenshots)) {
            $screenshots = $game->screenshots;
            $firstScreenshot = ($screenshots instanceof \Illuminate\Support\Collection) ? $screenshots->first() : ($screenshots[0] ?? null);

            return $this->getImageUrl($firstScreenshot, '1080p');
        }

        return '';
    }

    /**
     * Get trailer URL from videos.
     */
    protected function getTrailerUrl(Game $game): string
    {
        if (! empty($game->videos)) {
            $videos = $game->videos;
            if ($videos instanceof \Illuminate\Support\Collection) {
                $videos = $videos->toArray();
            }
            foreach ($videos as $video) {
                $videoId = is_array($video) ? ($video['video_id'] ?? null) : ($video->video_id ?? null);
                if ($videoId) {
                    return 'https://www.youtube.com/watch?v='.$videoId;
                }
            }
        }

        return '';
    }

    /**
     * Get age rating string from IGDB age ratings.
     */
    protected function getAgeRating(Game $game): string
    {
        $ageRatings = $game->age_ratings ?? [];
        if ($ageRatings instanceof \Illuminate\Support\Collection) {
            $ageRatings = $ageRatings->toArray();
        }

        if (empty($ageRatings)) {
            return 'Not Rated';
        }

        // ESRB ratings map
        $esrbMap = [
            6 => 'RP (Rating Pending)',
            7 => 'EC (Early Childhood)',
            8 => 'E (Everyone)',
            9 => 'E10+ (Everyone 10+)',
            10 => 'T (Teen)',
            11 => 'M (Mature 17+)',
            12 => 'AO (Adults Only)',
        ];

        // PEGI ratings map
        $pegiMap = [
            1 => 'PEGI 3',
            2 => 'PEGI 7',
            3 => 'PEGI 12',
            4 => 'PEGI 16',
            5 => 'PEGI 18',
        ];

        foreach ($ageRatings as $rating) {
            $category = is_array($rating) ? ($rating['category'] ?? 0) : ($rating->category ?? 0);
            $ratingValue = is_array($rating) ? ($rating['rating'] ?? 0) : ($rating->rating ?? 0);

            // Prefer ESRB
            if ($category === 1 && isset($esrbMap[$ratingValue])) {
                return $esrbMap[$ratingValue];
            }
            // Fall back to PEGI
            if ($category === 2 && isset($pegiMap[$ratingValue])) {
                return $pegiMap[$ratingValue];
            }
        }

        return 'Not Rated';
    }

    /**
     * Get PC release date from IGDB game data.
     */
    protected function getReleaseDate(Game $game): string
    {
        if (! empty($game->release_dates)) {
            foreach ($game->release_dates as $release) {
                if (isset($release['platform']) && $release['platform'] === 6 && isset($release['date'])) {
                    return Carbon::createFromTimestamp($release['date'])->format('Y-m-d');
                }
            }
            if (isset($game->release_dates[0]['date'])) {
                return Carbon::createFromTimestamp($game->release_dates[0]['date'])->format('Y-m-d');
            }
        }

        if (isset($game->first_release_date)) {
            if ($game->first_release_date instanceof Carbon) {
                return $game->first_release_date->format('Y-m-d');
            }
            if (is_numeric($game->first_release_date)) {
                return Carbon::createFromTimestamp($game->first_release_date)->format('Y-m-d');
            }
        }

        return now()->format('Y-m-d');
    }

    /**
     * Build review/summary text.
     */
    protected function buildReview(Game $game, array $developers): string
    {
        $review = $game->summary ?? '';
        if (empty($review) && isset($game->storyline)) {
            $review = $game->storyline;
        }

        $additionalInfo = [];
        if (! empty($developers)) {
            $additionalInfo[] = 'Developer: '.implode(', ', array_slice($developers, 0, 3));
        }
        if (! empty($game->game_modes)) {
            $gameModes = $game->game_modes;
            if ($gameModes instanceof \Illuminate\Support\Collection) {
                $modes = $gameModes->map(fn ($m) => is_array($m) ? ($m['name'] ?? '') : ($m->name ?? ''))->toArray();
            } else {
                $modes = array_map(fn ($m) => $m['name'] ?? '', $gameModes);
            }
            $additionalInfo[] = 'Modes: '.implode(', ', array_filter($modes));
        }
        if (! empty($additionalInfo) && ! empty($review)) {
            $review .= "\n\n".implode("\n", $additionalInfo);
        }

        return $review;
    }

    /**
     * Extract base title by removing common subtitle patterns.
     */
    protected function extractBaseTitle(string $title): string
    {
        $patterns = [
            '/\s*[-:]\s+.*$/',
            '/\s+(?:Episode|Chapter|Part)\s+\d+.*/i',
            '/\s+(?:Vol(?:ume)?\.?\s*\d+).*/i',
            '/\s+\d+$/',
        ];

        $baseTitle = $title;
        foreach ($patterns as $pattern) {
            $baseTitle = preg_replace($pattern, '', $baseTitle);
        }

        return trim($baseTitle);
    }

    /**
     * Normalize title for matching.
     */
    protected function normalizeForMatch(string $title): string
    {
        $t = mb_strtolower($title);
        $t = (string) preg_replace('/\b(game of the year|goty|definitive edition|deluxe edition|ultimate edition|complete edition|remastered|hd remaster|directors? cut|anniversary edition|update|patch|hotfix|incl(?:uding)? dlcs?|dlcs?|repack|rip|iso|crack(?:fix)?|beta|alpha)\b/i', ' ', $t);
        $t = (string) preg_replace('/\b(pc|gog|steam|x64|x86|win64|win32|mult[iy]?\d*|eng|english|fr|french|de|german|es|spanish|it|italian|pt|ptbr|portuguese|ru|russian|pl|polish|tr|turkish|nl|dutch|se|swedish|no|norwegian|da|danish|fi|finnish|jp|japanese|cn|chs|cht|ko|korean)\b/i', ' ', $t);
        $t = (string) preg_replace('/[^a-z0-9]+/i', ' ', $t);
        $t = trim(preg_replace('/\s{2,}/', ' ', $t));

        return $t;
    }

    /**
     * Compute similarity between two strings.
     */
    protected function computeSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 100.0;
        }
        $percent = 0.0;
        similar_text($a, $b, $percent);

        $levScore = 0.0;
        $len = max(strlen($a), strlen($b));
        if ($len > 0) {
            $dist = levenshtein($a, $b);
            if ($dist >= 0) {
                $levScore = (1 - ($dist / $len)) * 100.0;
            }
        }

        return max($percent, $levScore);
    }

    /**
     * Match genre string to known genres.
     */
    public function matchGenre(string $genre): string
    {
        $genreName = '';
        $a = str_replace('-', ' ', $genre);
        $tmpGenre = explode(',', $a);
        foreach ($tmpGenre as $tg) {
            $genreMatch = $this->isKnownGenre(ucwords(trim($tg)));
            if ($genreMatch !== false) {
                $genreName = (string) $genreMatch;
                break;
            }
            if (empty($genreName) && ! empty($tmpGenre[0])) {
                $genreName = trim($tmpGenre[0]);
            }
        }

        return $genreName;
    }

    /**
     * Check if genre is in known genres list.
     */
    public function isKnownGenre(string $gameGenre): bool|string
    {
        $knownGenres = [
            'Action', 'Adventure', 'Arcade', 'Board Games', 'Cards', 'Casino',
            'Flying', 'Puzzle', 'Racing', 'Rhythm', 'Role-Playing', 'RPG',
            'Simulation', 'Sports', 'Strategy', 'Trivia', 'Shooter', 'FPS',
            'Horror', 'Survival', 'Sandbox', 'Open World', 'Platformer', 'Fighting',
            'Stealth', 'MMO', 'MMORPG', 'Battle Royale', 'Roguelike', 'Roguelite',
            'Metroidvania', 'Visual Novel', 'Point & Click', 'Management',
            'City Builder', 'Tower Defense', 'Turn-Based', 'Real-Time',
            'Educational', 'Music', 'Party', 'Indie', 'Hack and Slash',
            'Souls-like', 'JRPG', 'ARPG', 'Tactical',
        ];

        return in_array($gameGenre, $knownGenres, true) ? $gameGenre : false;
    }

    /**
     * Clear lookup caches.
     */
    public function clearCache(): void
    {
        Log::info('IGDBService: Cache clear requested');
    }
}
