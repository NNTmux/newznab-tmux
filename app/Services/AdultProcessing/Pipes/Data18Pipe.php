<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * Data18 provider pipe.
 *
 * Handles movie information extraction from data18.com
 * Data18 is a comprehensive adult movie database with good structured data.
 */
class Data18Pipe extends AbstractAdultProviderPipe
{
    protected int $priority = 15; // Second priority after AEBN

    private const BASE_URL = 'https://www.data18.com';
    private const SEARCH_URL = '/search/?k=';

    protected string $directUrl = '';
    protected string $title = '';
    protected string $response = '';
    protected ?array $jsonLdData = null;

    public function getName(): string
    {
        return 'data18';
    }

    public function getDisplayName(): string
    {
        return 'Data18';
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

        // Try multiple container selectors for search results
        $containerSelectors = [
            'div.gen12',
            'div.movie-card',
            'div[class*="result"]',
            'a[href*="/movies/"]',
        ];

        foreach ($containerSelectors as $containerSelector) {
            $results = $this->getHtmlParser()->find($containerSelector);

            if (!empty($results)) {
                foreach ($results as $result) {
                    $link = null;
                    $title = '';

                    // If the container is a link itself
                    if (isset($result->href) && str_contains($result->href, '/movies/')) {
                        $link = $result;
                        $title = $result->title ?? trim($result->plaintext ?? '');
                    } else {
                        // Find link within container
                        $link = $result->findOne('a[href*="/movies/"]');
                        if ($link) {
                            $title = $link->title ?? trim($link->plaintext ?? '');
                        }
                    }

                    if ($link && isset($link->href) && !empty($title)) {
                        $similarity = $this->calculateSimilarity($movie, $title);

                        if ($similarity > $highestSimilarity) {
                            $highestSimilarity = $similarity;
                            $url = $link->href;
                            if (!str_starts_with($url, 'http')) {
                                $url = self::BASE_URL . $url;
                            }
                            $bestMatch = [
                                'title' => trim($title),
                                'url' => $url,
                                'similarity' => $similarity,
                            ];
                        }
                    }
                }

                if ($bestMatch !== null) {
                    break;
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

        // Title
        if (!empty($this->jsonLdData['name'])) {
            $results['title'] = $this->jsonLdData['name'];
        }

        // Synopsis/Description
        if (!empty($this->jsonLdData['description'])) {
            $results['synopsis'] = $this->jsonLdData['description'];
        }

        // Image/Cover
        if (!empty($this->jsonLdData['image'])) {
            $image = is_array($this->jsonLdData['image']) ? ($this->jsonLdData['image'][0] ?? '') : $this->jsonLdData['image'];
            if (!empty($image)) {
                $results['boxcover'] = $image;
            }
        }

        // Duration
        if (!empty($this->jsonLdData['duration'])) {
            $results['duration'] = $this->jsonLdData['duration'];
        }

        // Director
        if (!empty($this->jsonLdData['director'])) {
            $director = $this->jsonLdData['director'];
            if (is_array($director)) {
                $results['director'] = $director['name'] ?? ($director[0]['name'] ?? '');
            } else {
                $results['director'] = $director;
            }
        }

        // Actors
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

        // Genre
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

        // Try multiple selectors
        $selectors = [
            'img[itemprop=image]',
            'img.cover',
            'img[src*="cover"]',
            'div.cover img',
            'a.cover img',
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

                    // Try to find back cover
                    $backCover = str_replace(['front', '_f.', '_1.'], ['back', '_b.', '_2.'], $coverUrl);
                    if ($backCover !== $coverUrl) {
                        $res['backcover'] = $backCover;
                    }

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
            'div[itemprop=description]',
            'span[itemprop=description]',
            'div.synopsis',
            'div.description',
            'p.synopsis',
            'meta[name="description"]',
            'meta[property="og:description"]',
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

        $selectors = [
            'a[href*="/name/"]',
            'a[href*="/pornstars/"]',
            'span[itemprop=actor] a',
            'div.cast a',
            'div.performers a',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (!empty($elements)) {
                foreach ($elements as $element) {
                    $name = trim($element->plaintext ?? '');
                    if (!empty($name) && strlen($name) > 2) {
                        $cast[] = $name;
                    }
                }
                if (!empty($cast)) {
                    break;
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
            'a[href*="/category/"]',
            'a[href*="/genre/"]',
            'span[itemprop=genre]',
            'div.categories a',
            'div.tags a',
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
        $studioSelectors = [
            'a[href*="/studio/"]',
            'a[href*="/studios/"]',
            'span.studio a',
        ];

        foreach ($studioSelectors as $selector) {
            $studio = $this->getHtmlParser()->findOne($selector);
            if ($studio) {
                $res['studio'] = trim($studio->plaintext ?? '');
                break;
            }
        }

        // Look for release date
        $dateSelectors = [
            'span[itemprop=datePublished]',
            'time[datetime]',
            'span.date',
        ];

        foreach ($dateSelectors as $selector) {
            $date = $this->getHtmlParser()->findOne($selector);
            if ($date) {
                $res['releasedate'] = $date->datetime ?? trim($date->plaintext ?? '');
                break;
            }
        }

        // Look for director
        $directorSelectors = [
            'a[href*="/director/"]',
            'span.director a',
        ];

        foreach ($directorSelectors as $selector) {
            $director = $this->getHtmlParser()->findOne($selector);
            if ($director) {
                $res['director'] = trim($director->plaintext ?? '');
                break;
            }
        }

        return $res;
    }
}

