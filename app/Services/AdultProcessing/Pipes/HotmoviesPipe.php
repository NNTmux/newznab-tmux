<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * HotMovies provider pipe.
 *
 * Handles movie information extraction from hotmovies.com
 */
class HotmoviesPipe extends AbstractAdultProviderPipe
{
    protected int $priority = 50;

    private const BASE_URL = 'https://www.hotmovies.com';
    private const SEARCH_URL = '/search.php?words=';
    private const EXTRA_SEARCH = '&complete=on&search_in=video_title';

    protected string $directUrl = '';
    protected string $title = '';
    protected string $response = '';

    public function getName(): string
    {
        return 'hotm';
    }

    public function getDisplayName(): string
    {
        return 'HotMovies';
    }

    protected function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    protected function process(AdultProcessingPassable $passable): AdultProcessingResult
    {
        $movie = $passable->getCleanTitle();

        $searchResult = $this->search($movie);

        if ($searchResult === false) {
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

        $this->getHtmlParser()->loadHtml($this->response);

        $movieInfo = $this->getMovieInfo();

        if ($movieInfo === false) {
            return AdultProcessingResult::notFound($this->getName());
        }

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

        $searchUrl = self::BASE_URL . self::SEARCH_URL . urlencode($movie) . self::EXTRA_SEARCH;
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if ($response === false) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);

        // Try multiple result selectors
        $resultSelectors = [
            'h3[class=title] a[title]',
            'h3.title a',
            'div.movie-title a',
        ];

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($resultSelectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (!empty($elements)) {
                foreach ($elements as $ret) {
                    $title = $ret->title ?? $ret->plaintext ?? '';
                    $url = $ret->href ?? '';

                    if (empty($title) || empty($url)) {
                        continue;
                    }

                    $similarity = $this->calculateSimilarity($movie, $title);

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatch = [
                            'title' => trim($title),
                            'url' => str_starts_with($url, 'http') ? $url : self::BASE_URL . $url,
                        ];
                    }
                }

                // If we found results with this selector, don't try others
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

        // Get all the movie data
        $synopsis = $this->extractSynopsis();
        if (is_array($synopsis)) {
            $results = array_merge($results, $synopsis);
        }

        $productInfo = $this->extractProductInfo(true);
        if (is_array($productInfo)) {
            $results = array_merge($results, $productInfo);
        }

        $cast = $this->extractCast();
        if (is_array($cast)) {
            $results = array_merge($results, $cast);
        }

        $genres = $this->extractGenres();
        if (is_array($genres)) {
            $results = array_merge($results, $genres);
        }

        $covers = $this->extractCovers();
        if (is_array($covers)) {
            $results = array_merge($results, $covers);
        }

        if (empty($results)) {
            return false;
        }

        return $results;
    }

    protected function extractSynopsis(): array
    {
        $res = [];
        $res['synopsis'] = 'N/A';

        // Try multiple selectors
        $selectors = [
            '.video_description',
            'div.description',
            'div.synopsis',
            'meta[name="description"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret) {
                $text = $ret->innerText ?? $ret->plaintext ?? $ret->content ?? '';
                if (!empty(trim($text))) {
                    $res['synopsis'] = trim($text);

                    return $res;
                }
            }
        }

        return $res;
    }

    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];
        $studio = false;
        $director = false;

        if (($ret = $this->getHtmlParser()->find('div.page_video_info')) && !empty($ret->find('text'))) {
            $productinfo = [];

            foreach ($ret->find('text') as $e) {
                $e = trim($e->plaintext);
                $rArray = [',', '...', '&nbsp:'];
                $e = str_replace($rArray, '', $e);

                if (stripos($e, 'Studio:') !== false) {
                    $studio = true;
                }

                if (str_contains($e, 'Director:')) {
                    $director = true;
                    $e = null;
                }

                if ($studio === true) {
                    if ((stripos($e, 'Custodian of Records') === false) && stripos($e, 'Description') === false) {
                        if ($director === true && !empty($e)) {
                            $res['director'] = $e;
                            $e = null;
                            $director = false;
                        }
                        if (!empty($e)) {
                            $productinfo[] = $e;
                        }
                    } else {
                        break;
                    }
                }
            }

            if (is_array($productinfo)) {
                $res['productinfo'] = array_chunk($productinfo, 2, false);
            }
        }

        return $res;
    }

    protected function extractCast(): array
    {
        $res = [];
        $cast = [];

        // Prefer scoped search within stars container to avoid unrelated links
        if ($container = $this->getHtmlParser()->findOne('.stars')) {
            foreach ($container->find('a[title]') as $e) {
                $name = trim($e->title);
                $name = preg_replace('/\((.*)\)/', '', $name);
                $name = trim($name);
                if ($name !== '') {
                    $cast[] = $name;
                }
            }
        }

        // Fallback: anchors that look like performer links
        if (empty($cast)) {
            foreach ($this->getHtmlParser()->find('a[href*="/performers/"]') as $e) {
                $name = trim($e->plaintext);
                if ($name !== '') {
                    $cast[] = $name;
                }
            }
        }

        if (!empty($cast)) {
            $res['cast'] = array_values(array_unique($cast));
        }

        return $res;
    }

    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        if ($ret = $this->getHtmlParser()->findOne('div.categories')) {
            foreach ($ret->find('a') as $e) {
                if (str_contains($e->title, ' -> ')) {
                    $e = explode(' -> ', $e->plaintext);
                    $genres[] = trim($e[1]);
                }
            }
            $res['genres'] = $genres;
        }

        return $res;
    }

    protected function extractCovers(): array
    {
        $res = [];

        // Try multiple selectors
        $selectors = [
            'img#cover',
            'div#large_cover img',
            'img.boxcover',
            'div.product-image img',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret && isset($ret->src)) {
                $res['boxcover'] = trim($ret->src);
                $res['backcover'] = str_ireplace(['.cover', 'front'], ['.back', 'back'], trim($ret->src));

                return $res;
            }
        }

        return $res;
    }
}

