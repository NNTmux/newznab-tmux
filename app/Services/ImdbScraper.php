<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use voku\helper\HtmlDomParser;

class ImdbScraper
{
    protected const int SOFT_FAILURE_TTL_MINUTES = 30;

    protected const int HARD_FAILURE_TTL_HOURS = 6;

    protected Client $client;

    protected bool $lastRequestWasBlocked = false;

    public function __construct(?Client $client = null)
    {
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

    /**
     * Fetch a movie by IMDB numeric ID.
     *
     * @param  string  $id  Numeric part without 'tt'.
     * @return array<string, mixed>
     */
    public function fetchById(string $id): array|false
    {
        $this->lastRequestWasBlocked = false;

        $id = preg_replace('/[^0-9]/', '', $id) ?? '';
        if ($id === '' || strlen($id) < 5 || strlen($id) > 8) {
            return false;
        }

        $cacheKey = 'imdb_scrape_id_'.$id;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = 'https://www.imdb.com/title/tt'.$id.'/';

        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Referer' => 'https://www.imdb.com/',
                    'Upgrade-Insecure-Requests' => '1',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $html = (string) $response->getBody();

            if ($this->isWafResponse($statusCode, $html)) {
                $this->lastRequestWasBlocked = true;
                Cache::put($cacheKey, false, now()->addMinutes(self::SOFT_FAILURE_TTL_MINUTES));
                Log::notice('IMDb title fetch was challenged by WAF for tt'.$id);

                return false;
            }

            if ($statusCode >= 400 || trim($html) === '') {
                Cache::put($cacheKey, false, now()->addHours(self::HARD_FAILURE_TTL_HOURS));

                return false;
            }

            $data = $this->parseTitleHtml($html, $id);
            if ($data === false) {
                Cache::put($cacheKey, false, now()->addHours(self::HARD_FAILURE_TTL_HOURS));

                return false;
            }

            Cache::put($cacheKey, $data, now()->addDays(7));

            return $data;
        } catch (\Throwable $e) {
            Log::debug('IMDb fetch error tt'.$id.': '.$e->getMessage());
            Cache::put($cacheKey, false, now()->addHours(self::HARD_FAILURE_TTL_HOURS));

            return false;
        }
    }

    /**
     * Search IMDb suggestion API for a title.
     *
     * @return array<string, mixed>
     */
    public function search(string $query): array
    {
        $this->lastRequestWasBlocked = false;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $norm = strtolower(preg_replace('/[^a-z0-9 ]/i', '', $query) ?? '');
        $slug = preg_replace('/\s+/', '_', trim($norm)) ?? '';
        if ($slug === '') {
            return [];
        }

        $prefix = substr($slug, 0, 1);
        $cacheKey = 'imdb_search_'.md5($slug);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = 'https://v2.sg.media-imdb.com/suggestion/'.urlencode($prefix).'/'.urlencode($slug).'.json';

        try {
            $res = $this->client->get($url, [
                'headers' => [
                    'Accept' => 'application/json,text/plain,*/*',
                    'Origin' => 'https://www.imdb.com',
                    'Referer' => 'https://www.imdb.com/find/?q='.rawurlencode($query).'&s=tt',
                ],
            ]);
            $body = (string) $res->getBody();

            $results = [];
            if ($res->getStatusCode() === 200 && ! $this->isWafResponse($res->getStatusCode(), $body)) {
                $results = $this->parseSuggestionJson($body);
            }

            if ($results === []) {
                $htmlResults = $this->searchHtmlFallback($query);
                if ($htmlResults !== []) {
                    $results = $htmlResults;
                }
            }

            if ($results === []) {
                $ttl = $this->lastRequestWasBlocked
                    ? now()->addMinutes(self::SOFT_FAILURE_TTL_MINUTES)
                    : now()->addHours(self::HARD_FAILURE_TTL_HOURS);
                Cache::put($cacheKey, [], $ttl);

                return [];
            }

            Cache::put($cacheKey, $results, now()->addHours(12));

            return $results; // @phpstan-ignore return.type
        } catch (\Throwable $e) {
            Log::debug('IMDb search error '.$query.': '.$e->getMessage());
            Cache::put($cacheKey, [], now()->addHours(self::HARD_FAILURE_TTL_HOURS));

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTitleHtml(string $html, string $id): array|false
    {
        if ($this->isWafResponse(200, $html)) {
            $this->lastRequestWasBlocked = true;

            return false;
        }

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

    private function isWafResponse(int $statusCode, string $body): bool
    {
        if ($statusCode === 202) {
            return true;
        }

        if ($body === '') {
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
            $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (! is_array($decoded)) {
                continue;
            }

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

    private function extractType(array $jsonLd): string
    {
        $type = strtolower((string) ($jsonLd['@type'] ?? 'movie'));

        return match ($type) {
            'tvseries', 'tvminiseries', 'tvepisode', 'tvmovie' => 'tv',
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
     * @return array<int, array{imdbid: string, title: string, year: string}>
     */
    private function parseSuggestionJson(string $body): array
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return [];
        }

        $results = [];
        foreach (($json['d'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_string($row['id']) || ! str_starts_with($row['id'], 'tt')) {
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
            ];

            if (count($results) >= 25) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<int, array{imdbid: string, title: string, year: string}>
     */
    private function searchHtmlFallback(string $query): array
    {
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

                return [];
            }

            if ($statusCode >= 400 || trim($html) === '') {
                return [];
            }

            return $this->parseSearchHtml($html);
        } catch (\Throwable $e) {
            Log::debug('IMDb HTML search fallback failed for '.$query.': '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array<int, array{imdbid: string, title: string, year: string}>
     */
    private function parseSearchHtml(string $html): array
    {
        $results = [];

        if (preg_match_all('#<a[^>]+href=["\']/title/tt(?P<id>\d{5,8})/["\'][^>]*>(?P<title>.*?)</a>#is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $fullMatch) {
                $title = trim(html_entity_decode(strip_tags($matches['title'][$index][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($title === '') {
                    continue;
                }

                $offset = $fullMatch[1] + strlen($fullMatch[0]);
                $context = substr($html, $offset, 200);

                $results[] = [
                    'imdbid' => $matches['id'][$index][0],
                    'title' => $title,
                    'year' => $this->extractYearFromText(strip_tags($context)),
                ];

                if (count($results) >= 25) {
                    break;
                }
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

                    $context = trim(strip_tags((string) $html));
                    $results[] = [
                        'imdbid' => $matches['id'],
                        'title' => $title,
                        'year' => $this->extractYearFromText($context),
                    ];

                    if (count($results) >= 25) {
                        break;
                    }
                }
            }
        }

        $unique = [];
        foreach ($results as $result) {
            $unique[$result['imdbid']] = $result;
        }

        return array_values($unique);
    }
}
