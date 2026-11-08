<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MetadataSources;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use voku\helper\HtmlDomParser;

class ImdbScraper
{
    protected const int SOFT_FAILURE_TTL_MINUTES = 30;

    protected const int HARD_FAILURE_TTL_HOURS = 6;

    protected const string IMDBAPI_DEV_INTERVAL_CACHE_KEY = 'imdbapi_dev:min_interval';

    protected const string IMDBAPI_DEV_COOLDOWN_CACHE_KEY = 'imdbapi_dev:cooldown';

    protected const string WAF_BACKOFF_CACHE_KEY = 'imdb_scraper:waf_backoff';

    protected const string WAF_BLOCKED_CACHE_KEY = 'imdb_scraper:waf_blocked';

    protected const string WAF_PROBE_CACHE_KEY = 'imdb_scraper:waf_probe';

    protected const int WAF_PROBE_LOCK_SECONDS = 120;

    /**
     * Suggestion API title types ("qid") that are movies.
     */
    protected const array SUGGESTION_MOVIE_TYPES = ['movie', 'tvMovie', 'video'];

    /**
     * Title page failures worth retrying soon rather than caching for hours.
     */
    protected const array TRANSIENT_FAILURE_REASONS = ['html_exception', 'html_http_failure', 'html_empty_response'];

    protected Client $client;

    protected string $imdbApiDevBaseUrl;

    protected bool $lastRequestWasBlocked = false;

    protected ?string $lastFetchSource = null;

    protected ?string $lastFailureReason = null;

    protected ?string $lastFallbackFailureReason = null;

    protected bool $lastFetchWasSkipped = false;

    protected bool $lastFailureWasTransient = false;

    public function __construct(?Client $client = null)
    {
        $this->imdbApiDevBaseUrl = rtrim((string) config('nntmux_api.imdbapi_dev_base_url', 'https://api.imdbapi.dev'), '/');
        $this->client = $client ?? new Client([
            'timeout' => 10,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ],
        ]);
    }

    public function wasBlockedByWaf(): bool
    {
        return $this->lastRequestWasBlocked;
    }

    public function isScrapingEnabled(): bool
    {
        return MetadataSources::isAvailable(MetadataSources::IMDB_SCRAPER);
    }

    public function isImdbApiDevEnabled(): bool
    {
        return MetadataSources::isAvailable(MetadataSources::IMDBAPI_DEV);
    }

    /**
     * Whether either IMDb source (scraping or the imdbapi.dev fallback) is switched on.
     */
    public function isEnabled(): bool
    {
        return $this->isScrapingEnabled() || $this->isImdbApiDevEnabled();
    }

    /**
     * Whether imdb.com is considered blocked, i.e. scraping is backing off or waiting for a probe.
     */
    public function isWafBackoffActive(): bool
    {
        return Cache::has(self::WAF_BLOCKED_CACHE_KEY);
    }

    /**
     * Whether the last fetchById() made no request because every source was off or paused.
     */
    public function wasSkipped(): bool
    {
        return $this->lastFetchWasSkipped;
    }

    public function getLastFetchSource(): ?string
    {
        return $this->lastFetchSource;
    }

    public function getLastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function getLastFallbackFailureReason(): ?string
    {
        return $this->lastFallbackFailureReason;
    }

    /**
     * Fetch a movie by IMDB numeric ID.
     *
     * @param  string  $id  Numeric part without 'tt'.
     * @return array<string, mixed>
     */
    public function fetchById(string $id): array|false
    {
        $this->lastRequestWasBlocked = false;
        $this->lastFetchSource = null;
        $this->lastFailureReason = null;
        $this->lastFallbackFailureReason = null;
        $this->lastFetchWasSkipped = false;
        $this->lastFailureWasTransient = false;

        $id = preg_replace('/[^0-9]/', '', $id) ?? '';
        if ($id === '' || strlen($id) < 5 || strlen($id) > 8) {
            $this->lastFailureReason = 'invalid_id';

            return false;
        }

        // IMDb ids have at least 7 digits; "211915" is tt0211915.
        $id = str_pad($id, 7, '0', STR_PAD_LEFT);

        $cacheKey = 'imdb_scrape_id_'.$id;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (! $this->isScrapingEnabled() || ! $this->acquireScrapeSlot()) {
            return $this->fetchWithoutScraping($id, $cacheKey);
        }

        $data = $this->fetchTitlePage($id);
        if (is_array($data)) {
            $this->lastFetchSource = 'imdb_html';
            Cache::put($cacheKey, $data, now()->addDays(7));

            return $data;
        }

        $this->lastFailureReason = $data;
        $this->lastRequestWasBlocked = $data === 'waf_block';
        if ($data === 'not_found') {
            Cache::put($cacheKey, false, now()->addHours(self::HARD_FAILURE_TTL_HOURS));

            return false;
        }

        $fallbackData = $this->fetchFromImdbApiDev($id);
        if ($fallbackData !== false) {
            $this->lastFetchSource = 'imdbapi_dev';
            $this->lastFailureReason = null;
            $this->lastFallbackFailureReason = null;
            Cache::put($cacheKey, $fallbackData, now()->addDays(7));

            return $fallbackData;
        }

        $this->lastFailureWasTransient = $data === 'waf_block' || in_array($data, self::TRANSIENT_FAILURE_REASONS, true);
        $ttl = $this->lastFailureWasTransient
            ? now()->addMinutes(self::SOFT_FAILURE_TTL_MINUTES)
            : now()->addHours(self::HARD_FAILURE_TTL_HOURS);
        Cache::put($cacheKey, false, $ttl);

        return false;
    }

    /**
     * Whether the last fetchById() failure may succeed on a retry soon (block, timeout, server error).
     */
    public function wasTransientFailure(): bool
    {
        return $this->lastFailureWasTransient;
    }

    /**
     * Load and parse the imdb.com title page.
     *
     * @return array<string, mixed>|string The title data, or why it failed (waf_block, not_found, html_*).
     */
    private function fetchTitlePage(string $id): array|string
    {
        try {
            $response = $this->client->get('https://www.imdb.com/title/tt'.$id.'/', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Referer' => 'https://www.imdb.com/',
                    'Upgrade-Insecure-Requests' => '1',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::debug('IMDb fetch error tt'.$id.': '.$e->getMessage());
            Cache::forget(self::WAF_PROBE_CACHE_KEY);

            return 'html_exception';
        }

        $statusCode = $response->getStatusCode();
        $html = (string) $response->getBody();

        if ($this->isWafResponse($statusCode, $html)) {
            $this->startWafBackoff();

            return 'waf_block';
        }

        $this->endWafBackoff();

        if ($statusCode === 404 || $statusCode === 410) {
            return 'not_found';
        }

        if ($statusCode >= 400 || trim($html) === '') {
            return trim($html) === '' ? 'html_empty_response' : 'html_http_failure';
        }

        return $this->parseTitleHtml($html, $id) ?: 'html_parse_failure';
    }

    /**
     * Search the IMDb suggestion API for movies matching a title.
     *
     * Falls back to the imdb.com find page only when the suggestion request itself failed.
     *
     * @return list<array{imdbid: string, title: string, year: string, type: string, cover: string}>
     */
    public function search(string $query): array
    {
        $this->lastRequestWasBlocked = false;

        $query = trim($query);
        $slug = $this->suggestionSlug($query);
        if ($slug === '') {
            return [];
        }

        $cacheKey = 'imdb_search_'.md5($slug);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (! $this->isScrapingEnabled()) {
            return [];
        }

        $results = $this->fetchSuggestions($slug, $query) ?? $this->searchHtmlFallback($query);

        if ($results === null) {
            Cache::put($cacheKey, [], now()->addMinutes(self::SOFT_FAILURE_TTL_MINUTES));

            return [];
        }

        $ttl = $results === [] ? now()->addHours(self::HARD_FAILURE_TTL_HOURS) : now()->addHours(12);
        Cache::put($cacheKey, $results, $ttl);

        return $results;
    }

    /**
     * Suggestion API slug: ASCII, lowercase, words joined by underscores ("Spider-Man" => "spider_man").
     */
    private function suggestionSlug(string $query): string
    {
        $text = str_replace(["'", '’', '`'], '', Str::ascii($query));
        $text = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? ''));

        return preg_replace('/\s+/', '_', $text) ?? '';
    }

    /**
     * @return list<array{imdbid: string, title: string, year: string, type: string, cover: string}>|null Null when the request failed.
     */
    private function fetchSuggestions(string $slug, string $query): ?array
    {
        $url = 'https://v2.sg.media-imdb.com/suggestion/'.urlencode($slug[0]).'/'.urlencode($slug).'.json';

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'application/json,text/plain,*/*',
                    'Origin' => 'https://www.imdb.com',
                    'Referer' => 'https://www.imdb.com/find/?q='.rawurlencode($query).'&s=tt',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $this->parseSuggestionJson((string) $response->getBody());
        } catch (\Throwable $e) {
            Log::debug('IMDb suggestion search failed for '.$query.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTitleHtml(string $html, string $id): array|false
    {
        $jsonLd = $this->extractPrimaryJsonLdEntity($html);
        $dom = HtmlDomParser::str_get_html($html);

        $title = $this->extractTitle($jsonLd, $dom);
        if ($title === '') {
            return false;
        }

        $year = $this->extractYearFromEntity($jsonLd);
        if ($year === '') {
            $year = $this->extractYearFromDom($dom);
        }

        $plot = $this->extractPlot($jsonLd, $dom);
        $rating = $this->extractRating($jsonLd, $dom);
        $cover = $this->extractCover($jsonLd, $dom);
        $genres = $this->extractGenres($jsonLd, $dom);
        $directors = $this->extractPeople($jsonLd['director'] ?? null);
        $actors = array_slice($this->extractPeople($jsonLd['actor'] ?? null), 0, 10);
        $language = $this->extractLanguage($jsonLd, $dom);
        $type = $this->extractType($jsonLd);

        if ($dom !== false) {
            if ($directors === []) {
                $directors = $this->extractPeopleFromDom($dom, [
                    "li[data-testid='title-pc-principal-credit']:contains(Director) a",
                    "li[data-testid='title-pc-principal-credit']:contains(Creator) a",
                ]);
            }

            if ($actors === []) {
                $actors = array_slice($this->extractPeopleFromDom($dom, [
                    "div[data-testid='title-cast-item'] a[data-testid='title-cast-item__actor']",
                ]), 0, 10);
            }
        }

        return [
            'imdbid' => $id,
            'title' => $title,
            'known_titles' => $this->normalizeStringList([$title, ...$this->normalizeStringList($jsonLd['alternateName'] ?? [])]),
            'year' => $year,
            'plot' => $plot,
            'rating' => $rating,
            'cover' => $cover,
            'genre' => $genres,
            'director' => $directors,
            'actors' => $actors,
            'language' => $language,
            'type' => $type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFromImdbApiDev(string $id): array|false
    {
        if (! $this->canUseImdbApiDev()) {
            return false;
        }

        if ($this->imdbApiDevBaseUrl === '') {
            $this->lastFallbackFailureReason = 'fallback_disabled';

            return false;
        }

        try {
            $this->markImdbApiDevAttempt();

            $response = $this->client->get($this->imdbApiDevBaseUrl.'/titles/tt'.$id, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 429) {
                $this->lastFallbackFailureReason = 'fallback_rate_limited';
                $this->activateImdbApiDevCooldown();

                return false;
            }

            if ($response->getStatusCode() !== 200) {
                $this->lastFallbackFailureReason = 'fallback_http_failure';
                if ($response->getStatusCode() >= 500) {
                    $this->activateImdbApiDevCooldown();
                }

                return false;
            }

            $payload = json_decode((string) $response->getBody(), true);
            if (! is_array($payload)) {
                $this->lastFallbackFailureReason = 'fallback_invalid_json';

                return false;
            }

            $mapped = $this->mapImdbApiDevTitle($payload, $id);
            if ($mapped === false) {
                $this->lastFallbackFailureReason = 'fallback_invalid_payload';

                return false;
            }

            return $mapped;
        } catch (ConnectException $e) {
            $this->lastFallbackFailureReason = 'fallback_unreachable';
            // A DNS or connect failure won't clear in minutes; pause long and warn once per pause.
            $cooldownSeconds = max(1, (int) config('nntmux_api.imdbapi_dev_unreachable_cooldown_seconds', 21600));
            Cache::put(self::IMDBAPI_DEV_COOLDOWN_CACHE_KEY, true, now()->addSeconds($cooldownSeconds));
            Log::warning('imdbapi.dev unreachable, pausing the IMDb fallback for '.$cooldownSeconds.' seconds: '.$e->getMessage());

            return false;
        } catch (\Throwable $e) {
            Log::debug('imdbapi.dev fetch error tt'.$id.': '.$e->getMessage());
            $this->lastFallbackFailureReason = 'fallback_exception';
            $this->activateImdbApiDevCooldown();

            return false;
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchWithoutScraping(string $id, string $cacheKey): array|false
    {
        $this->lastRequestWasBlocked = $this->isScrapingEnabled();
        $this->lastFailureWasTransient = $this->lastRequestWasBlocked;
        $this->lastFailureReason = $this->lastRequestWasBlocked ? 'waf_backoff' : 'scraper_disabled';

        if (! $this->canUseImdbApiDev()) {
            $this->lastFetchWasSkipped = true;

            return false;
        }

        $fallbackData = $this->fetchFromImdbApiDev($id);
        if ($fallbackData !== false) {
            $this->lastFetchSource = 'imdbapi_dev';
            $this->lastFailureReason = null;
            $this->lastFallbackFailureReason = null;
            Cache::put($cacheKey, $fallbackData, now()->addDays(7));

            return $fallbackData;
        }

        Cache::put($cacheKey, false, now()->addMinutes(self::SOFT_FAILURE_TTL_MINUTES));

        return false;
    }

    /**
     * While blocked, let a single worker probe imdb.com once the back-off interval has expired.
     */
    private function acquireScrapeSlot(): bool
    {
        if (! Cache::has(self::WAF_BLOCKED_CACHE_KEY)) {
            return true;
        }

        if (Cache::has(self::WAF_BACKOFF_CACHE_KEY)) {
            return false;
        }

        return Cache::add(self::WAF_PROBE_CACHE_KEY, true, self::WAF_PROBE_LOCK_SECONDS);
    }

    private function startWafBackoff(): void
    {
        $minutes = max(1, (int) config('nntmux_api.imdb_scraper_block_backoff_minutes', 60));
        Cache::put(self::WAF_BACKOFF_CACHE_KEY, true, now()->addMinutes($minutes));
        Cache::forget(self::WAF_PROBE_CACHE_KEY);

        if (Cache::add(self::WAF_BLOCKED_CACHE_KEY, true, now()->addDays(7))) {
            Log::warning('IMDb scraping blocked by WAF, pausing IMDb scraping for '.$minutes.' minutes');
        } else {
            Cache::put(self::WAF_BLOCKED_CACHE_KEY, true, now()->addDays(7));
            Log::debug('IMDb still blocked by WAF, backing off for '.$minutes.' minutes');
        }
    }

    private function endWafBackoff(): void
    {
        if (Cache::pull(self::WAF_BLOCKED_CACHE_KEY) === null) {
            return;
        }

        Cache::forget(self::WAF_BACKOFF_CACHE_KEY);
        Cache::forget(self::WAF_PROBE_CACHE_KEY);
        Log::info('IMDb scraping no longer blocked by WAF, lookups resumed');
    }

    private function canUseImdbApiDev(): bool
    {
        if (! $this->isImdbApiDevEnabled()) {
            $this->lastFallbackFailureReason = 'fallback_disabled';

            return false;
        }

        if (Cache::has(self::IMDBAPI_DEV_COOLDOWN_CACHE_KEY)) {
            $this->lastFallbackFailureReason = 'fallback_cooldown_active';

            return false;
        }

        $minInterval = max(0, (int) config('nntmux_api.imdbapi_dev_min_interval_seconds', 15));
        if ($minInterval > 0 && Cache::has(self::IMDBAPI_DEV_INTERVAL_CACHE_KEY)) {
            $this->lastFallbackFailureReason = 'fallback_min_interval_active';

            return false;
        }

        return true;
    }

    private function markImdbApiDevAttempt(): void
    {
        $minInterval = max(0, (int) config('nntmux_api.imdbapi_dev_min_interval_seconds', 15));
        if ($minInterval <= 0) {
            return;
        }

        Cache::put(self::IMDBAPI_DEV_INTERVAL_CACHE_KEY, true, now()->addSeconds($minInterval));
    }

    private function activateImdbApiDevCooldown(): void
    {
        $cooldownSeconds = max(0, (int) config('nntmux_api.imdbapi_dev_cooldown_seconds', 300));
        if ($cooldownSeconds <= 0) {
            return;
        }

        Cache::put(self::IMDBAPI_DEV_COOLDOWN_CACHE_KEY, true, now()->addSeconds($cooldownSeconds));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapImdbApiDevTitle(array $payload, string $id): array|false
    {
        $title = trim((string) ($payload['primaryTitle'] ?? $payload['title'] ?? ''));
        if ($title === '') {
            return false;
        }

        $year = trim((string) ($payload['startYear'] ?? $payload['year'] ?? ''));
        $plot = trim((string) ($payload['plot'] ?? $payload['description'] ?? ''));
        $rating = trim((string) ($payload['rating']['aggregateRating'] ?? $payload['rating'] ?? ''));
        $cover = $this->normalizeImageUrl($payload['primaryImage'] ?? $payload['image'] ?? '');
        $genres = $this->normalizeStringList($payload['genres'] ?? []);
        $directors = $this->extractApiDevPeople($payload['directors'] ?? []);
        $actors = array_slice($this->extractApiDevPeople($payload['stars'] ?? $payload['cast'] ?? []), 0, 10);
        $language = $this->extractApiDevLanguages($payload['spokenLanguages'] ?? []);
        $type = $this->normalizeApiDevType((string) ($payload['type'] ?? 'movie'));

        return [
            'imdbid' => $id,
            'title' => $title,
            'known_titles' => $this->normalizeStringList([$title, (string) ($payload['originalTitle'] ?? '')]),
            'year' => $year,
            'plot' => $plot,
            'rating' => $rating,
            'cover' => $cover,
            'genre' => $genres,
            'director' => $directors,
            'actors' => $actors,
            'language' => $language,
            'type' => $type,
        ];
    }

    /**
     * Whether imdb.com refused the request: a WAF challenge (202), a block (403) or rate limiting (429).
     * Challenge markers only count on pages without title data, since a real title page can mention them.
     */
    private function isWafResponse(int $statusCode, string $body): bool
    {
        if (in_array($statusCode, [202, 403, 429], true)) {
            return true;
        }

        if ($body === '' || str_contains($body, 'application/ld+json')) {
            return false;
        }

        return Str::contains($body, [
            'window.awsWafCookieDomainList',
            'window.gokuProps',
            'challenge.js',
            'captcha',
            'awswaf',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPrimaryJsonLdEntity(string $html): array
    {
        if (! preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);
            if (! is_array($decoded)) {
                continue;
            }

            // IMDb escapes quotes inside values ("Schindler&apos;s List"); decoding them before
            // json_decode would break the JSON, so decode the values instead.
            array_walk_recursive($decoded, static function (mixed &$value): void {
                if (is_string($value)) {
                    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            });

            $entity = $this->findTitleEntity($decoded);
            if ($entity !== []) {
                return $entity;
            }
        }

        return [];
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function findTitleEntity(array $payload): array
    {
        if ($this->looksLikeTitleEntity($payload)) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }

            $entity = $this->findTitleEntity($value);
            if ($entity !== []) {
                return $entity;
            }
        }

        return [];
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function looksLikeTitleEntity(array $payload): bool
    {
        $type = strtolower((string) ($payload['@type'] ?? ''));

        return in_array($type, ['movie', 'tvseries', 'tvminiseries', 'tvepisode', 'tvmovie', 'video'], true)
            || (isset($payload['name']) && (isset($payload['actor']) || isset($payload['director']) || isset($payload['aggregateRating'])));
    }

    private function extractTitle(array $jsonLd, HtmlDomParser|false $dom): string
    {
        $title = trim((string) ($jsonLd['name'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        if ($dom !== false) {
            $titleNode = $dom->findOne('h1');
            $title = trim((string) ($titleNode?->text() ?? ''));
            if ($title !== '') {
                return preg_replace('/\s+\(.*?\)$/', '', $title) ?? $title;
            }

            $ogTitle = $dom->findOne("meta[property='og:title']");
            $title = trim((string) ($ogTitle?->getAttribute('content') ?? ''));
            if ($title !== '') {
                return preg_replace('/\s+-\s+IMDb$/', '', $title) ?? $title;
            }
        }

        return '';
    }

    private function extractYearFromEntity(array $jsonLd): string
    {
        $year = $this->extractYearFromText((string) ($jsonLd['datePublished'] ?? ''));
        if ($year !== '') {
            return $year;
        }

        return $this->extractYearFromText((string) ($jsonLd['dateCreated'] ?? ''));
    }

    private function extractYearFromDom(HtmlDomParser|false $dom): string
    {
        if ($dom === false) {
            return '';
        }

        $selectors = [
            "span[data-testid='title-details-releasedate'] a",
            "span[data-testid='title-details-releasedate']",
            "ul[data-testid='hero-title-block__metadata'] li",
        ];

        foreach ($selectors as $selector) {
            foreach ($dom->find($selector) as $node) {
                $year = $this->extractYearFromText($node->text());
                if ($year !== '') {
                    return $year;
                }
            }
        }

        return '';
    }

    private function extractPlot(array $jsonLd, HtmlDomParser|false $dom): string
    {
        $plot = trim((string) ($jsonLd['description'] ?? ''));
        if ($plot !== '') {
            return $plot;
        }

        if ($dom === false) {
            return '';
        }

        $plotNode = $dom->findOne("span[data-testid='plot-l']") ?: $dom->findOne("span[data-testid='plot-xl']");
        $plot = trim((string) ($plotNode?->text() ?? ''));
        if ($plot !== '') {
            return $plot;
        }

        $html = $dom->html();
        if (preg_match('/data-testid=["\']plot-(?:l|xl)["\'][^>]*>(.*?)<\/span>/is', $html, $matches) === 1) {
            $plot = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($plot !== '') {
                return $plot;
            }
        }

        $descriptionNode = $dom->findOne("meta[name='description']");

        return trim((string) ($descriptionNode?->getAttribute('content') ?? ''));
    }

    private function extractRating(array $jsonLd, HtmlDomParser|false $dom): string
    {
        $rating = trim((string) ($jsonLd['aggregateRating']['ratingValue'] ?? ''));
        if ($rating !== '') {
            return $rating;
        }

        if ($dom === false) {
            return '';
        }

        $ratingNode = $dom->findOne("div[data-testid='hero-rating-bar__aggregate-rating__score'] span");
        $ratingText = trim((string) ($ratingNode?->text() ?? ''));

        return preg_match('/\d+(?:\.\d+)?/', $ratingText, $matches) === 1 ? $matches[0] : '';
    }

    private function extractCover(array $jsonLd, HtmlDomParser|false $dom): string
    {
        $cover = $this->normalizeImageUrl($jsonLd['image'] ?? '');
        if ($cover !== '') {
            return $cover;
        }

        if ($dom === false) {
            return '';
        }

        $posterNode = $dom->findOne("div[data-testid='hero-media__poster'] img") ?: $dom->findOne("meta[property='og:image']");
        $cover = (string) ($posterNode?->getAttribute('src') ?? $posterNode?->getAttribute('content') ?? '');

        return $this->normalizeImageUrl($cover);
    }

    /**
     * @return array<int, string>
     */
    private function extractGenres(array $jsonLd, HtmlDomParser|false $dom): array
    {
        $genres = $this->normalizeStringList($jsonLd['genre'] ?? []);
        if ($genres !== []) {
            return $genres;
        }

        if ($dom === false) {
            return [];
        }

        $genres = [];
        foreach ($dom->find("div[data-testid='genres'] a") as $genreNode) {
            $genre = trim($genreNode->text());
            if ($genre !== '') {
                $genres[] = $genre;
            }
        }

        return array_values(array_unique($genres));
    }

    /**
     * @return array<int, string>
     */
    private function extractPeople(mixed $value): array
    {
        $people = [];
        foreach ($this->normalizeValueList($value) as $item) {
            $name = '';
            if (is_array($item)) {
                $name = trim((string) ($item['name'] ?? ''));
            } elseif (is_string($item)) {
                $name = trim($item);
            }

            if ($name !== '') {
                $people[] = $name;
            }
        }

        return array_values(array_unique($people));
    }

    /**
     * @param  array<int, string>  $selectors
     * @return array<int, string>
     */
    private function extractPeopleFromDom(HtmlDomParser $dom, array $selectors): array
    {
        $people = [];

        foreach ($selectors as $selector) {
            foreach ($dom->find($selector) as $node) {
                $name = trim($node->text());
                if ($name !== '') {
                    $people[] = $name;
                }
            }
        }

        return array_values(array_unique($people));
    }

    /**
     * @return array<int, string>
     */
    private function extractApiDevPeople(mixed $value): array
    {
        $people = [];

        foreach ($this->normalizeValueList($value) as $item) {
            if (is_array($item)) {
                $name = trim((string) ($item['displayName'] ?? $item['name'] ?? ''));
            } else {
                $name = trim((string) $item);
            }

            if ($name !== '') {
                $people[] = $name;
            }
        }

        return array_values(array_unique($people));
    }

    private function extractLanguage(array $jsonLd, HtmlDomParser|false $dom): string
    {
        $languages = $this->normalizeStringList($jsonLd['inLanguage'] ?? []);
        if ($languages !== []) {
            return implode(', ', $languages);
        }

        if ($dom === false) {
            return '';
        }

        $languages = [];
        foreach ($dom->find("li[data-testid='title-details-languages'] a") as $languageNode) {
            $language = trim($languageNode->text());
            if ($language !== '') {
                $languages[] = $language;
            }
        }

        return implode(', ', array_values(array_unique($languages)));
    }

    private function extractApiDevLanguages(mixed $value): string
    {
        $languages = [];

        foreach ($this->normalizeValueList($value) as $item) {
            if (is_array($item)) {
                $language = trim((string) ($item['name'] ?? $item['displayName'] ?? ''));
            } else {
                $language = trim((string) $item);
            }

            if ($language !== '') {
                $languages[] = $language;
            }
        }

        return implode(', ', array_values(array_unique($languages)));
    }

    private function extractType(array $jsonLd): string
    {
        $type = strtolower((string) ($jsonLd['@type'] ?? 'movie'));

        return match ($type) {
            'tvseries', 'tvminiseries', 'tvepisode' => 'tv',
            default => 'movie',
        };
    }

    private function normalizeApiDevType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'tvseries', 'tv_series', 'tv mini series', 'tv_mini_series', 'tvminiseries', 'tvspecial', 'tv_special', 'tvepisode', 'tv_episode' => 'tv',
            default => 'movie',
        };
    }

    private function extractYearFromText(string $text): string
    {
        return preg_match('/(19|20)\d{2}/', $text, $matches) === 1 ? $matches[0] : '';
    }

    private function normalizeImageUrl(mixed $image): string
    {
        $url = '';

        if (is_string($image)) {
            $url = trim($image);
        } elseif (is_array($image)) {
            $url = trim((string) ($image['url'] ?? $image[0]['url'] ?? $image[0] ?? ''));
        }

        if ($url !== '' && str_contains($url, '._V1_')) {
            $url = preg_replace('/\._V1_.*\./', '.', $url) ?? $url;
        }

        return $url;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        $items = [];
        foreach ($this->normalizeValueList($value) as $item) {
            if (is_string($item)) {
                $string = trim($item);
                if ($string !== '') {
                    $items[] = $string;
                }
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeValueList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    /**
     * Movie rows from a suggestion API answer. Series, episodes, shorts and games are skipped.
     *
     * @return list<array{imdbid: string, title: string, year: string, type: string, cover: string}>|null Null when the body is not suggestion JSON.
     */
    private function parseSuggestionJson(string $body): ?array
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return null;
        }

        $results = [];
        foreach (($json['d'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_string($row['id']) || ! str_starts_with($row['id'], 'tt')) {
                continue;
            }

            $type = (string) ($row['qid'] ?? '');
            if ($type !== '' && ! in_array($type, self::SUGGESTION_MOVIE_TYPES, true)) {
                continue;
            }

            $title = trim((string) ($row['l'] ?? ''));
            if ($title === '') {
                continue;
            }

            $results[] = [
                'imdbid' => substr($row['id'], 2),
                'title' => $title,
                'year' => trim((string) ($row['y'] ?? '')),
                'type' => $type === '' ? '' : 'movie',
                'cover' => $this->normalizeImageUrl($row['i']['imageUrl'] ?? ''),
            ];

            if (count($results) >= 25) {
                break;
            }
        }

        return $results;
    }

    /**
     * Search the imdb.com find page. Only used when the suggestion API failed; it shares the WAF back-off with title pages.
     *
     * @return list<array{imdbid: string, title: string, year: string, type: string, cover: string}>|null Null when the page could not be fetched.
     */
    private function searchHtmlFallback(string $query): ?array
    {
        if (! $this->acquireScrapeSlot()) {
            $this->lastRequestWasBlocked = true;

            return null;
        }

        $url = 'https://www.imdb.com/find/?q='.rawurlencode($query).'&s=tt';

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Referer' => 'https://www.imdb.com/',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $html = (string) $response->getBody();

            if ($this->isWafResponse($statusCode, $html)) {
                $this->lastRequestWasBlocked = true;
                $this->startWafBackoff();

                return null;
            }

            $this->endWafBackoff();

            if ($statusCode >= 400 || trim($html) === '') {
                return null;
            }

            return $this->parseSearchHtml($html);
        } catch (\Throwable $e) {
            Log::debug('IMDb HTML search fallback failed for '.$query.': '.$e->getMessage());
            Cache::forget(self::WAF_PROBE_CACHE_KEY);

            return null;
        }
    }

    /**
     * @return list<array{imdbid: string, title: string, year: string, type: string, cover: string}>
     */
    private function parseSearchHtml(string $html): array
    {
        $results = [];

        if (preg_match_all('#<a[^>]+href=["\']/title/tt(?P<id>\d{5,8})/(?:\?[^"\']*)?["\'][^>]*>(?P<title>.*?)</a>#is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $fullMatch) {
                $title = trim(html_entity_decode(strip_tags($matches['title'][$index][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($title === '') {
                    continue;
                }

                // The year follows the link; stop at the next result so it can't borrow that one's year.
                $offset = $fullMatch[1] + strlen($fullMatch[0]);
                $nextLink = $matches[0][$index + 1][1] ?? null;
                $length = min(500, $nextLink === null ? 500 : $nextLink - $offset);
                $context = substr($html, $offset, max(0, $length));

                $results[] = $this->searchRow($matches['id'][$index][0], $title, $this->extractYearFromText(strip_tags($context)));
            }
        }

        if ($results === []) {
            $dom = HtmlDomParser::str_get_html($html);
            if ($dom !== false) {
                foreach ($dom->find("a[href^='/title/tt']") as $link) {
                    $href = (string) $link->getAttribute('href');
                    if (! preg_match('#/title/tt(?P<id>\d{5,8})/#', $href, $matches)) {
                        continue;
                    }

                    $title = trim($link->text());
                    if ($title === '') {
                        continue;
                    }

                    $row = $link->parentNode();
                    $results[] = $this->searchRow($matches['id'], $title, $this->extractYearFromText((string) $row?->text()));
                }
            }
        }

        $unique = [];
        foreach ($results as $result) {
            $unique[$result['imdbid']] ??= $result;
        }

        return array_slice(array_values($unique), 0, 25);
    }

    /**
     * @return array{imdbid: string, title: string, year: string, type: string, cover: string}
     */
    private function searchRow(string $imdbId, string $title, string $year): array
    {
        return ['imdbid' => $imdbId, 'title' => $title, 'year' => $year, 'type' => '', 'cover' => ''];
    }
}
