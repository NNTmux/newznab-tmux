<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * IAFD (Internet Adult Film Database) provider pipe.
 *
 * Handles movie information extraction from iafd.com
 * IAFD is a comprehensive database with excellent metadata coverage.
 */
class IafdPipe extends AbstractAdultProviderPipe
{
    protected int $priority = 12; // High priority - good database

    private const BASE_URL = 'https://www.iafd.com';
    private const SEARCH_URL = '/results.asp?searchtype=comprehensive&searchstring=';

    protected string $directUrl = '';
    protected string $title = '';
    protected string $response = '';
    protected ?array $jsonLdData = null;

    public function getName(): string
    {
        return 'iafd';
    }

    public function getDisplayName(): string
    {
        return 'Internet Adult Film Database';
    }

    protected function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    protected function process(AdultProcessingPassable $passable): AdultProcessingResult
    {
        $movie = $passable->getCleanTitle();

        // Check cache first
        $cached = $this->getCachedSearch($movie);
        if ($cached !== null) {
            if ($cached === false) {
                return AdultProcessingResult::notFound($this->getName());
            }
            return AdultProcessingResult::matched(
                $cached['title'] ?? $movie,
                $this->getName(),
                $cached
            );
        }

        $searchResult = $this->search($movie);

        if ($searchResult === false) {
            $this->cacheSearchResult($movie, false);
            $this->outputNotFound();
            return AdultProcessingResult::notFound($this->getName());
        }

        $this->title = $searchResult['title'];
        $this->directUrl = $searchResult['url'];

        // Fetch the movie details page
        $this->response = $this->fetchHtml($this->directUrl, $this->cookie);

        if ($this->response === false) {
            return AdultProcessingResult::failed('Failed to fetch movie details page', $this->getName());
        }

        // Try to extract JSON-LD data first
        $this->jsonLdData = $this->extractJsonLd($this->response);

        $this->getHtmlParser()->loadHtml($this->response);

        $movieInfo = $this->getMovieInfo();

        if ($movieInfo === false) {
            $this->cacheSearchResult($movie, false);
            return AdultProcessingResult::notFound($this->getName());
        }

        // Cache the successful result
        $this->cacheSearchResult($movie, $movieInfo);

        $this->outputMatch($this->title);

        return AdultProcessingResult::matched(
            $this->title,
            $this->getName(),
            $movieInfo
        );
    }

    protected function search(string $movie): array|false
    {
        if (empty($movie)) {
            return false;
        }

        $searchUrl = self::BASE_URL . self::SEARCH_URL . urlencode($movie);
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if ($response === false) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);

        $bestMatch = null;
        $highestSimilarity = 0;

        // Look for movie results table
        $movieResults = $this->getHtmlParser()->find('table#titleresult tr');

        if (empty($movieResults)) {
            // Try alternative selectors
            $movieResults = $this->getHtmlParser()->find('div.panel-body a[href*="/title.rme"]');
        }

        foreach ($movieResults as $result) {
            $link = null;
            $title = '';

            // Check if this is a table row
            if ($result->tag === 'tr') {
                $link = $result->findOne('a[href*="/title.rme"]');
                if ($link) {
                    $title = $link->title ?? trim($link->plaintext ?? '');
                }
            } else {
                // Direct link element
                $link = $result;
                $title = $link->title ?? trim($link->plaintext ?? '');
            }

            if ($link && isset($link->href) && !empty($title)) {
                $similarity = $this->calculateSimilarity($movie, $title);

                if ($similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $url = $link->href;
                    if (!str_starts_with($url, 'http')) {
                        $url = self::BASE_URL . '/' . ltrim($url, '/');
                    }
                    $bestMatch = [
                        'title' => trim($title),
                        'url' => $url,
                        'similarity' => $similarity,
                    ];
                }
            }
        }

        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            return $bestMatch;
        }

        return false;
    }

    protected function getMovieInfo(): array|false
    {
        $results = [];

        if (!empty($this->directUrl)) {
            if (!empty($this->title)) {
                $results['title'] = $this->title;
            }
            $results['directurl'] = $this->directUrl;
        }

        // Try to get data from JSON-LD first (most reliable)
        if ($this->jsonLdData !== null) {
            $results = array_merge($results, $this->extractFromJsonLd());
        }

        // Get all the movie data (HTML fallback)
        $synopsis = $this->extractSynopsis();
        if (is_array($synopsis) && !empty($synopsis)) {
            $results = array_merge($results, $synopsis);
        }

        $productInfo = $this->extractProductInfo(true);
        if (is_array($productInfo) && !empty($productInfo)) {
            $results = array_merge($results, $productInfo);
        }

        $cast = $this->extractCast();
        if (is_array($cast) && !empty($cast)) {
            $results = array_merge($results, $cast);
        }

        $genres = $this->extractGenres();
        if (is_array($genres) && !empty($genres)) {
            $results = array_merge($results, $genres);
        }

        $covers = $this->extractCovers();
        if (is_array($covers) && !empty($covers)) {
            $results = array_merge($results, $covers);
        }

        if (empty($results) || (empty($results['title'] ?? '') && empty($results['boxcover'] ?? ''))) {
            return false;
        }

        return $results;
    }

    /**
     * Extract data from JSON-LD structured data.
     */
    protected function extractFromJsonLd(): array
    {
        $results = [];

        if ($this->jsonLdData === null) {
            return $results;
        }

        // Standard JSON-LD extraction
        if (!empty($this->jsonLdData['name'])) {
            $results['title'] = $this->jsonLdData['name'];
        }

        if (!empty($this->jsonLdData['description'])) {
            $results['synopsis'] = $this->jsonLdData['description'];
        }

        if (!empty($this->jsonLdData['image'])) {
            $image = is_array($this->jsonLdData['image']) ? ($this->jsonLdData['image'][0] ?? '') : $this->jsonLdData['image'];
            if (!empty($image)) {
                $results['boxcover'] = $image;
            }
        }

        if (!empty($this->jsonLdData['director'])) {
            $director = $this->jsonLdData['director'];
            if (is_array($director)) {
                $results['director'] = $director['name'] ?? ($director[0]['name'] ?? '');
            } else {
                $results['director'] = $director;
            }
        }

        if (!empty($this->jsonLdData['actor'])) {
            $actors = $this->jsonLdData['actor'];
            $cast = [];
            if (is_array($actors)) {
                foreach ($actors as $actor) {
                    if (is_array($actor) && !empty($actor['name'])) {
                        $cast[] = $actor['name'];
                    } elseif (is_string($actor)) {
                        $cast[] = $actor;
                    }
                }
            }
            if (!empty($cast)) {
                $results['cast'] = $cast;
            }
        }

        if (!empty($this->jsonLdData['genre'])) {
            $genres = $this->jsonLdData['genre'];
            if (is_array($genres)) {
                $results['genres'] = $genres;
            } else {
                $results['genres'] = [$genres];
            }
        }

        return $results;
    }

    protected function extractCovers(): array
    {
        $res = [];

        // IAFD typically uses specific cover image selectors
        $selectors = [
            'div#titlecover img',
            'img#titlecover',
            'div.coverbox img',
            'img[src*="cover"]',
            'meta[property="og:image"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret) {
                $coverUrl = $ret->src ?? $ret->content ?? null;

                if (!empty($coverUrl)) {
                    if (str_starts_with($coverUrl, '//')) {
                        $coverUrl = 'https:' . $coverUrl;
                    } elseif (!str_starts_with($coverUrl, 'http')) {
                        $coverUrl = self::BASE_URL . '/' . ltrim($coverUrl, '/');
                    }

                    $res['boxcover'] = $coverUrl;
                    return $res;
                }
            }
        }

        return $res;
    }

    protected function extractSynopsis(): array
    {
        $res = [];

        $selectors = [
            'div#synopsis',
            'div.synopsis',
            'td.syno',
            'p[class*="synopsis"]',
            'meta[name="description"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret) {
                $text = $ret->plaintext ?? $ret->content ?? '';
                if (!empty(trim($text))) {
                    $res['synopsis'] = trim($text);
                    return $res;
                }
            }
        }

        return $res;
    }

    protected function extractCast(): array
    {
        $res = [];
        $cast = [];

        // IAFD has a specific cast table structure
        $castTable = $this->getHtmlParser()->findOne('table#perfcast, div#perfcast');

        if ($castTable) {
            $performers = $castTable->find('a[href*="/person.rme"]');
            foreach ($performers as $performer) {
                $name = trim($performer->plaintext ?? '');
                if (!empty($name) && strlen($name) > 2) {
                    $cast[] = $name;
                }
            }
        }

        // Fallback to general performer links
        if (empty($cast)) {
            $selectors = [
                'a[href*="/person.rme"]',
                'a[href*="/performer/"]',
            ];

            foreach ($selectors as $selector) {
                $elements = $this->getHtmlParser()->find($selector);
                if (!empty($elements)) {
                    foreach ($elements as $element) {
                        $name = trim($element->plaintext ?? '');
                        if (!empty($name) && strlen($name) > 2 && !str_contains(strtolower($name), 'director')) {
                            $cast[] = $name;
                        }
                    }
                    if (!empty($cast)) {
                        break;
                    }
                }
            }
        }

        if (!empty($cast)) {
            $res['cast'] = array_unique($cast);
        }

        return $res;
    }

    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        $selectors = [
            'a[href*="/genre.rme"]',
            'div.genres a',
            'span.genre a',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (!empty($elements)) {
                foreach ($elements as $element) {
                    $text = trim($element->plaintext ?? '');
                    if (!empty($text) && strlen($text) > 1) {
                        $genres[] = $text;
                    }
                }
                if (!empty($genres)) {
                    break;
                }
            }
        }

        if (!empty($genres)) {
            $res['genres'] = array_unique($genres);
        }

        return $res;
    }

    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];

        // Look for studio
        $studio = $this->getHtmlParser()->findOne('a[href*="/studio.rme"]');
        if ($studio) {
            $res['studio'] = trim($studio->plaintext ?? '');
        }

        // Look for distributor
        $distributor = $this->getHtmlParser()->findOne('a[href*="/distrib.rme"]');
        if ($distributor) {
            $res['distributor'] = trim($distributor->plaintext ?? '');
        }

        // Look for release year
        if (preg_match('/\((\d{4})\)/', $this->response, $yearMatch)) {
            $res['year'] = $yearMatch[1];
        }

        // Look for director
        $directorSelectors = [
            'a[href*="/person.rme"][title*="director"]',
            'p:contains("Director") a',
        ];

        foreach ($directorSelectors as $selector) {
            $director = $this->getHtmlParser()->findOne($selector);
            if ($director) {
                $res['director'] = trim($director->plaintext ?? '');
                break;
            }
        }

        // Try to extract from info table
        $infoRows = $this->getHtmlParser()->find('table.biodata tr, div.biodata p');
        foreach ($infoRows as $row) {
            $text = $row->plaintext ?? '';
            if (stripos($text, 'Director:') !== false) {
                $parts = explode(':', $text, 2);
                if (count($parts) === 2) {
                    $res['director'] = trim($parts[1]);
                }
            }
            if (stripos($text, 'Studio:') !== false) {
                $parts = explode(':', $text, 2);
                if (count($parts) === 2 && empty($res['studio'] ?? '')) {
                    $res['studio'] = trim($parts[1]);
                }
            }
            if (stripos($text, 'Minutes:') !== false || stripos($text, 'Runtime:') !== false) {
                $parts = explode(':', $text, 2);
                if (count($parts) === 2) {
                    $res['runtime'] = trim($parts[1]);
                }
            }
        }

        return $res;
    }
}

