<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * AEBN (Adult Entertainment Broadcast Network) provider pipe.
 *
 * Handles movie information extraction from straight.theater.aebn.net
 * with support for JSON-LD structured data and multiple fallback selectors.
 */
class AebnPipe extends AbstractAdultProviderPipe
{
    protected int $priority = 10;

    private const BASE_URL = 'https://straight.theater.aebn.net';

    private const SEARCH_URL = '/dispatcher/fts?theaterId=13992&genreId=101&locale=en&count=30&imageType=Large&targetSearchMode=basic&isAdvancedSearch=false&isFlushAdvancedSearchCriteria=false&sortType=Relevance&userQuery=title%3A+%2B';

    private const TRAILER_URL = '/dispatcher/previewPlayer?locale=en&theaterId=13992&genreId=101&movieId=';

    protected string $directUrl = '';

    protected string $title = '';

    /**
     * @var array<string, mixed>
     */
    protected array $res = [];

    protected string $response = '';

    /**
     * @var array<string, mixed>
     */
    protected ?array $jsonLdData = null;

    public function getName(): string
    {
        return 'aebn';
    }

    public function getDisplayName(): string
    {
        return 'Adult Entertainment Broadcast Network';
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

        if ($this->response === false) { // @phpstan-ignore identical.alwaysFalse
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

    /**
     * @return array<string, mixed>
     */
    protected function search(string $movie): array|false
    {
        if (empty($movie)) {
            return false;
        }

        $searchUrl = self::BASE_URL.self::SEARCH_URL.urlencode($movie);
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if ($response === false) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);

        $bestMatch = null;
        $highestSimilarity = 0;
        $i = 1;

        // Try multiple container selectors for search results
        $containerSelectors = [
            'div.movie',
            'div.search-result',
            'div[class*="movie"]',
            'article.movie',
        ];

        $movies = [];
        foreach ($containerSelectors as $containerSelector) {
            $movies = $this->getHtmlParser()->find($containerSelector);
            if (! empty($movies) && (is_countable($movies) ? count($movies) > 0 : true)) {
                break;
            }
        }

        foreach ($movies as $mov) {
            // Try multiple selector patterns for the title link
            $selectors = [
                'a#FTSMovieSearch_link_title_detail_'.$i,
                'a.title-link',
                'a[href*="/movie/"]',
                'h3 a',
                'a[title]',
            ];

            $ret = null;
            foreach ($selectors as $selector) {
                $ret = $mov->findOne($selector);
                if ($ret && isset($ret->href)) {
                    break;
                }
            }

            if ($ret && isset($ret->href)) {
                $title = $ret->title ?? trim($ret->plaintext ?? '');

                if (! empty($title)) {
                    $similarity = $this->calculateSimilarity($movie, $title);

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatch = [
                            'title' => trim($title),
                            'url' => self::BASE_URL.html_entity_decode($ret->href),
                            'similarity' => $similarity,
                        ];
                    }
                }
            }
            $i++;
        }

        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            return $bestMatch;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMovieInfo(): array|false
    {
        $results = [];

        if (! empty($this->directUrl)) {
            if (! empty($this->title)) {
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
        if (! empty($synopsis)) {
            $results = array_merge($results, $synopsis);
        }

        $productInfo = $this->extractProductInfo(true);
        if (! empty($productInfo)) {
            $results = array_merge($results, $productInfo);
        }

        $cast = $this->extractCast();
        if (! empty($cast)) {
            $results = array_merge($results, $cast);
        }

        $genres = $this->extractGenres();
        if (! empty($genres)) {
            $results = array_merge($results, $genres);
        }

        $covers = $this->extractCovers();
        if (! empty($covers)) {
            $results = array_merge($results, $covers);
        }

        $trailers = $this->extractTrailers();
        if (! empty($trailers)) {
            $results = array_merge($results, $trailers);
        }

        if (empty($results) || (empty($results['title'] ?? '') && empty($results['boxcover'] ?? ''))) {
            return false;
        }

        return $results;
    }

    /**
     * Extract data from JSON-LD structured data.
     *
     * @return array<string, mixed>
     */
    protected function extractFromJsonLd(): array
    {
        $results = [];

        if ($this->jsonLdData === null) {
            return $results;
        }

        // Title
        if (! empty($this->jsonLdData['name'])) {
            $results['title'] = $this->jsonLdData['name'];
        }

        // Synopsis/Description
        if (! empty($this->jsonLdData['description'])) {
            $results['synopsis'] = $this->jsonLdData['description'];
        }

        // Image/Cover
        if (! empty($this->jsonLdData['image'])) {
            $image = is_array($this->jsonLdData['image']) ? ($this->jsonLdData['image'][0] ?? '') : $this->jsonLdData['image'];
            if (! empty($image)) {
                $results['boxcover'] = $image;
                $results['backcover'] = str_ireplace(['xlf.jpg', 'front'], ['xlb.jpg', 'back'], $image);
            }
        }

        // Duration
        if (! empty($this->jsonLdData['duration'])) {
            $results['duration'] = $this->jsonLdData['duration'];
        }

        // Director
        if (! empty($this->jsonLdData['director'])) {
            $director = $this->jsonLdData['director'];
            if (is_array($director)) {
                $results['director'] = $director['name'] ?? ($director[0]['name'] ?? '');
            } else {
                $results['director'] = $director;
            }
        }

        // Actors
        if (! empty($this->jsonLdData['actor'])) {
            $actors = $this->jsonLdData['actor'];
            $cast = [];
            if (is_array($actors)) {
                foreach ($actors as $actor) {
                    if (is_array($actor) && ! empty($actor['name'])) {
                        $cast[] = $actor['name'];
                    } elseif (is_string($actor)) {
                        $cast[] = $actor;
                    }
                }
            }
            if (! empty($cast)) {
                $results['cast'] = $cast;
            }
        }

        // Genre
        if (! empty($this->jsonLdData['genre'])) {
            $genres = $this->jsonLdData['genre'];
            if (is_array($genres)) {
                $results['genres'] = $genres;
            } else {
                $results['genres'] = [$genres];
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractTrailers(): array
    {
        $res = [];

        // Try multiple selectors
        $selectors = [
            'a[itemprop=trailer]',
            'a[href*="previewPlayer"]',
            'a.trailer-link',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->find($selector, 0);
            if ($ret && isset($ret->href) && preg_match('/movieId=(?<movieid>\d+)/', trim($ret->href), $hits)) {
                $movieid = $hits['movieid'];
                $res['trailers']['url'] = self::BASE_URL.self::TRAILER_URL.$movieid;

                return $res;
            }
        }

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractCovers(): array
    {
        $res = [];

        // Try multiple selectors
        $selectors = [
            'img[itemprop=thumbnailUrl]',
            'img[itemprop=image]',
            'div#md-boxCover img',
            'div.boxcover img',
            'img.boxcover',
            'img[src*="boxcover"]',
            'meta[property="og:image"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret) { // @phpstan-ignore if.alwaysTrue
                $coverUrl = $ret->src ?? $ret->content ?? null;

                if (! empty($coverUrl)) {
                    // Ensure URL has protocol
                    if (str_starts_with($coverUrl, '//')) {
                        $coverUrl = 'https:'.$coverUrl;
                    }

                    // Get high-resolution versions
                    $res['boxcover'] = str_ireplace(['160w.jpg', '120w.jpg', '_small', '_thumb'], ['xlf.jpg', 'xlf.jpg', '', ''], $coverUrl);
                    $res['backcover'] = str_ireplace(['160w.jpg', '120w.jpg', 'xlf.jpg', 'front'], ['xlb.jpg', 'xlb.jpg', 'xlb.jpg', 'back'], $res['boxcover']);

                    return $res;
                }
            }
        }

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        // Try multiple selectors
        $selectors = [
            'div.md-detailsCategories a[itemprop=genre]',
            'a[itemprop=genre]',
            'span[itemprop=genre]',
            'div.categories a',
            'a[href*="/category/"]',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (! empty($elements)) {
                foreach ($elements as $genre) {
                    $text = trim($genre->plaintext ?? '');
                    if (! empty($text)) {
                        $genres[] = $text;
                    }
                }
                if (! empty($genres)) {
                    break;
                }
            }
        }

        if (! empty($genres)) {
            $res['genres'] = array_unique($genres);
        }

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractCast(): array
    {
        $res = [];
        $cast = [];

        // Try multiple selectors
        $selectors = [
            'div.starsFull span[itemprop=name]',
            'span[itemprop=name]',
            'a[href*="/stars/"]',
            'div.cast a',
            'div.performers a',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (! empty($elements)) {
                foreach ($elements as $star) {
                    $text = trim($star->plaintext ?? '');
                    if (! empty($text) && strlen($text) > 2) {
                        $cast[] = $text;
                    }
                }
                if (! empty($cast)) {
                    break;
                }
            }
        }

        // Fallback: try detailsLink div
        if (empty($cast)) {
            $ret = $this->getHtmlParser()->findOne('div.detailsLink');
            if ($ret && ! ($ret instanceof \voku\helper\SimpleHtmlDomNodeBlank)) { // @phpstan-ignore booleanAnd.leftAlwaysTrue
                foreach ($ret->find('span') as $star) {
                    $text = $star->plaintext ?? '';
                    if (str_contains($text, '/More/') && str_contains($text, '/Stars/')) {
                        $cast[] = trim($text);
                    }
                }
            }
        }

        if (! empty($cast)) {
            $res['cast'] = array_unique($cast);
        }

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];

        // Try multiple selectors
        $selectors = [
            'div#md-detailsLeft',
            'div.movie-details',
            'div.product-info',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->find($selector, 0);
            if ($ret) {
                $productinfo = [];
                foreach ($ret->find('div') as $div) {
                    foreach ($div->find('span') as $span) {
                        $text = rawurldecode($span->plaintext ?? '');
                        $text = preg_replace('/&nbsp;/', '', $text);
                        $text = trim($text);
                        if (! empty($text)) {
                            $productinfo[] = $text;
                        }
                    }
                }

                if (! empty($productinfo)) {
                    if (false !== $key = array_search('Running Time:', $productinfo, false)) {
                        unset($productinfo[$key + 2]);
                    }

                    if (false !== $key = array_search('Director:', $productinfo, false)) {
                        $res['director'] = $productinfo[$key + 1] ?? '';
                        unset($productinfo[$key], $productinfo[$key + 1]);
                    }

                    $res['productinfo'] = array_chunk(array_values($productinfo), 2, false);
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractSynopsis(): array
    {
        $res = [];

        // Try multiple selectors in order of preference
        $selectors = [
            'span[itemprop=about]',
            'span[itemprop=description]',
            'div[itemprop=description]',
            'div.movieDetailDescription',
            'div.synopsis',
            'p.description',
            'meta[name="description"]',
            'meta[property="og:description"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret) { // @phpstan-ignore if.alwaysTrue
                $text = $ret->plaintext ?? $ret->content ?? '';
                if (! empty(trim($text))) {
                    $text = trim($text);
                    $text = preg_replace('/^Description:\s*/i', '', $text);
                    $res['synopsis'] = $text;

                    return $res;
                }
            }
        }

        return $res;
    }
}
