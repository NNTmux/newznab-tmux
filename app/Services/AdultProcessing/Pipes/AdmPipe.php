<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * Adult DVD Marketplace (ADM) provider pipe.
 *
 * Handles movie information extraction from adultdvdmarketplace.com
 */
class AdmPipe extends AbstractAdultProviderPipe
{
    protected int $priority = 30;

    private const BASE_URL = 'https://www.adultdvdmarketplace.com';
    private const SEARCH_URL = '/xcart/adult_dvd/advanced_search.php?sort_by=relev&title=';

    protected string $directUrl = '';
    protected string $title = '';
    protected string $response = '';

    public function getName(): string
    {
        return 'adm';
    }

    public function getDisplayName(): string
    {
        return 'Adult DVD Marketplace';
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

        $searchUrl = self::BASE_URL . self::SEARCH_URL . urlencode($movie);
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if ($response === false) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);
        $check = $this->getHtmlParser()->find('img[rel=license]');

        if (empty($check)) {
            return false;
        }

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($check as $ret) {
            if (!isset($ret->alt) || !isset($ret->src)) {
                continue;
            }

            $title = trim($ret->alt, '"');
            $title = str_replace('/XXX/', '', $title);

            $similarity = $this->calculateSimilarity($movie, $title);

            if ($similarity > $highestSimilarity && preg_match('/\/(?<sku>\d+)\.jpg$/i', $ret->src, $hits)) {
                $highestSimilarity = $similarity;
                $bestMatch = [
                    'title' => trim($title),
                    'sku' => $hits['sku'],
                ];
            }
        }

        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            return [
                'title' => $bestMatch['title'],
                'url' => self::BASE_URL . '/dvd_view_' . $bestMatch['sku'] . '.html',
            ];
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

    protected function extractCovers(): array
    {
        $res = [];
        $baseUrl = 'https://www.adultdvdmarketplace.com/';

        // Try fancybox link first
        if ($ret = $this->getHtmlParser()->findOne('a[rel=fancybox-button]')) {
            if (isset($ret->href) && preg_match('/images\/.*[\d]+\.jpg$/i', $ret->href, $hits)) {
                $res['boxcover'] = str_starts_with($hits[0], 'http')
                    ? $hits[0]
                    : $baseUrl . $hits[0];
                $res['backcover'] = str_ireplace('/front/', '/back/', $res['boxcover']);

                return $res;
            }
        }

        // Try license image
        if ($ret = $this->getHtmlParser()->findOne('img[rel=license]')) {
            if (isset($ret->src) && preg_match('/images\/.*[\d]+\.jpg$/i', $ret->src, $hits)) {
                $res['boxcover'] = str_starts_with($hits[0], 'http')
                    ? $hits[0]
                    : $baseUrl . $hits[0];

                return $res;
            }
        }

        return $res;
    }

    protected function extractSynopsis(): array
    {
        $res = [];
        $res['synopsis'] = 'N/A';

        // Try to find Description heading
        foreach ($this->getHtmlParser()->find('h3') as $heading) {
            if (trim($heading->plaintext) === 'Description') {
                $nextElement = $heading->next_sibling();
                if ($nextElement && !empty(trim($nextElement->plaintext))) {
                    $res['synopsis'] = trim($nextElement->plaintext);
                    return $res;
                }
            }
        }

        // Fallback: Try meta description
        $meta = $this->getHtmlParser()->findOne('meta[name="description"]');
        if ($meta && isset($meta->content) && !empty(trim($meta->content))) {
            $res['synopsis'] = trim($meta->content);
        }

        return $res;
    }

    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];

        foreach ($this->getHtmlParser()->find('ul.list-unstyled li') as $li) {
            $category = explode(':', $li->plaintext);
            switch (trim($category[0])) {
                case 'Director':
                    $res['director'] = trim($category[1]);
                    break;
                case 'Format':
                case 'Studio':
                case 'Released':
                case 'SKU':
                    $res['productinfo'][trim($category[0])] = trim($category[1]);
            }
        }

        return $res;
    }

    protected function extractCast(): array
    {
        $res = [];
        $cast = [];

        foreach ($this->getHtmlParser()->find('h3') as $heading) {
            if (trim($heading->plaintext) === 'Cast') {
                $next = $heading->nextSibling();
                while ($next) {
                    if ($next->nodeName === 'h3') {
                        break;
                    }
                    if (isset($next->href) && preg_match('/search_performerid/', $next->href)) {
                        $cast[] = trim($next->plaintext);
                    }
                    $next = $next->nextSibling();
                }
            }
        }

        $res['cast'] = array_unique($cast);

        return $res;
    }

    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        foreach ($this->getHtmlParser()->find('ul.list-unstyled') as $li) {
            $category = explode(':', $li->plaintext);
            if (trim($category[0]) === 'Category') {
                foreach (explode(',', $category[1]) as $genre) {
                    $genres[] = trim($genre);
                }
                $res['genres'] = $genres;
            }
        }

        return $res;
    }
}

