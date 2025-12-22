<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * Popporn provider pipe.
 *
 * Handles movie information extraction from popporn.com
 */
class PoppornPipe extends AbstractAdultProviderPipe
{
    protected int $priority = 20;

    private const BASE_URL = 'https://www.popporn.com';
    private const SEARCH_ENDPOINT = '/search?q=';

    protected string $directUrl = '';
    protected string $title = '';
    protected string $response = '';

    public function getName(): string
    {
        return 'pop';
    }

    public function getDisplayName(): string
    {
        return 'PopPorn';
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

        // First, establish a session by visiting the AgeConfirmation endpoint to set cookies
        $this->acceptAgeVerification();

        $searchUrl = self::BASE_URL . self::SEARCH_ENDPOINT . urlencode($movie);
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if (empty($response)) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);

        $bestMatch = null;
        $highestSimilarity = 0;

        // Try multiple selector patterns for search results
        $resultSelectors = [
            'div.product-info a, div.title a',
            'div.product-title a',
            'h3.product-title a',
        ];

        foreach ($resultSelectors as $selector) {
            $results = $this->getHtmlParser()->find($selector);

            if (!empty($results)) {
                foreach ($results as $result) {
                    $title = $result->title ?? $result->plaintext;
                    $url = $result->href;

                    if (!empty($title)) {
                        $similarity = $this->calculateSimilarity($movie, $title);

                        if ($similarity > $highestSimilarity) {
                            $highestSimilarity = $similarity;
                            $bestMatch = [
                                'title' => trim($title),
                                'url' => str_starts_with($url, 'http') ? $url : self::BASE_URL . $url,
                            ];
                        }
                    }
                }

                break;
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

        $trailers = $this->extractTrailers();
        if (is_array($trailers)) {
            $results = array_merge($results, $trailers);
        }

        if (empty($results)) {
            return false;
        }

        return $results;
    }

    protected function extractCovers(): array
    {
        $res = [];

        // Method 1: Try structured data
        if (preg_match('/"image":\s*"(.*?)"/is', $this->response, $match)) {
            $res['boxcover'] = trim($match[1]);
            // Try to determine backcover from boxcover pattern
            if (stripos(trim($match[1]), '_aa') !== false) {
                $res['backcover'] = str_ireplace('_aa', '_bb', trim($match[1]));
            } else {
                $res['backcover'] = str_ireplace('.jpg', '_b.jpg', trim($match[1]));
            }

            return $res;
        }

        // Method 2: Try multiple selectors
        $selectors = [
            'div[id=box-art], a[rel=box-art]',
            'img.front',
            'div.box-cover img',
            'div.product-image img',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->getHtmlParser()->findOne($selector)) {
                $res['boxcover'] = $ret->href ?? $ret->src;

                // Try to determine backcover
                if (stripos($res['boxcover'], '_aa') !== false) {
                    $res['backcover'] = str_ireplace('_aa', '_bb', $res['boxcover']);
                } else {
                    $res['backcover'] = str_ireplace('.jpg', '_b.jpg', $res['boxcover']);
                }

                return $res;
            }
        }

        return $res;
    }

    protected function extractSynopsis(): array
    {
        $res = [];

        // Method 1: Try structured data
        if (preg_match('/"description":\s*"(.*?)"/is', $this->response, $match)) {
            $res['synopsis'] = trim(html_entity_decode(str_replace('\\u', '\\u', $match[1])));
            return $res;
        }

        // Method 2: Try multiple selectors
        $selectors = [
            'div[id=product-info] h3[class=highlight] + *',
            'div.product-description',
            'div.synopsis',
            'meta[name="description"]',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->getHtmlParser()->findOne($selector)) {
                $text = $ret->plaintext ?? $ret->content;

                // Filter out "POPPORN EXCLUSIVE" text
                if (stripos(trim($text), 'POPPORN EXCLUSIVE') !== false) {
                    if ($ret->next_sibling()) {
                        $text = trim($ret->next_sibling()->plaintext);
                    }
                }

                if (!empty($text)) {
                    $res['synopsis'] = trim($text);
                    return $res;
                }
            }
        }

        return $res;
    }

    protected function extractTrailers(): array
    {
        $res = [];

        // Method 1: Try structured data
        if (preg_match('/"contentUrl":\s*"(.*?)"/is', $this->response, $match)) {
            $url = trim($match[1]);
            if (!empty($url)) {
                $res['trailers']['url'] = $url;
                return $res;
            }
        }

        // Method 2: Modern video embeds
        $videoSelectors = [
            'video source',
            'iframe[src*="trailer"]',
            'video[src]',
        ];

        foreach ($videoSelectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret && isset($ret->src) && !empty(trim($ret->src))) {
                $res['trailers']['url'] = trim($ret->src);
                return $res;
            }
        }

        return $res;
    }

    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];
        $productInfo = [];
        $director = '';

        // Method 1: Try structured data
        if (preg_match('/"director":\s*{[^}]*"name":\s*"(.*?)"/is', $this->response, $match)) {
            $director = trim($match[1]);
        }

        // Method 2: Look for product details in various formats
        $selectors = [
            'div#lside',
            'div.product-details',
            'div.product-info',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->getHtmlParser()->findOne($selector)) {
                // Extract country information
                $country = false;
                $rawInfo = [];

                foreach ($ret->find('text') as $e) {
                    $e = trim($e->innertext);
                    $e = str_replace([', ', '...', '&nbsp;'], '', $e);

                    if (stripos($e, 'Country:') !== false) {
                        $country = true;
                    }

                    if ($country === true) {
                        if (stripos($e, 'addthis_config') === false) {
                            if (!empty($e)) {
                                $rawInfo[] = $e;
                            }
                        } else {
                            break;
                        }
                    }
                }

                if (!empty($rawInfo)) {
                    $productInfo = array_chunk($rawInfo, 2, false);
                    break;
                }
            }
        }

        $res['productinfo'] = $productInfo;
        $res['director'] = $director;

        // Get extras if requested
        if ($extras === true) {
            $features = false;
            $extrasData = [];

            $featureSelectors = [
                'ul.stock-information',
                'div.features',
                'div.extras',
            ];

            foreach ($featureSelectors as $selector) {
                if ($ret = $this->getHtmlParser()->findOne($selector)) {
                    foreach ($ret->find('li') as $e) {
                        $text = trim($e->plaintext);
                        if ($text === 'Features:') {
                            $features = true;
                            continue;
                        }

                        if ($features === true && !empty($text)) {
                            $extrasData[] = $text;
                        }
                    }

                    if (!empty($extrasData)) {
                        $res['extras'] = $extrasData;
                        break;
                    }
                }
            }
        }

        return $res;
    }

    protected function extractCast(): array
    {
        $res = [];
        $cast = [];
        $director = '';

        // Method 1: Try structured data
        if (preg_match_all('/"actor":\s*{[^}]*"name":\s*"(.*?)"/is', $this->response, $matches)) {
            foreach ($matches[1] as $actor) {
                $cast[] = trim($actor);
            }
        }

        if (preg_match('/"director":\s*{[^}]*"name":\s*"(.*?)"/is', $this->response, $match)) {
            $director = trim($match[1]);
        }

        // Method 2: Try multiple selectors
        if (empty($cast)) {
            $castSelectors = [
                'div.cast a',
                'div.stars a',
                'div.performers a',
            ];

            foreach ($castSelectors as $selector) {
                $elements = $this->getHtmlParser()->find($selector);
                if (!empty($elements)) {
                    foreach ($elements as $element) {
                        $cast[] = trim($element->plaintext);
                    }
                    break;
                }
            }
        }

        $res['cast'] = array_unique(array_filter($cast));
        $res['director'] = $director;

        return $res;
    }

    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        // Method 1: Try structured data
        if (preg_match_all('/"genre":\s*"(.*?)"/is', $this->response, $matches)) {
            foreach ($matches[1] as $genre) {
                $genres[] = trim($genre);
            }
        }

        // Method 2: Try multiple selectors
        if (empty($genres)) {
            $selectors = [
                'div[id=thekeywords] a',
                'p[class=keywords] a',
                'div.categories a',
                'div.tags a',
            ];

            foreach ($selectors as $selector) {
                $elements = $this->getHtmlParser()->find($selector);
                if (!empty($elements)) {
                    foreach ($elements as $e) {
                        $genres[] = trim($e->plaintext);
                    }
                    break;
                }
            }
        }

        $res['genres'] = array_unique(array_filter($genres));

        return $res;
    }

    /**
     * Accept age verification by visiting the confirmation endpoint.
     * PopPorn uses a redirect-based age verification system.
     */
    protected function acceptAgeVerification(): void
    {
        try {
            // PopPorn requires visiting the AgeConfirmation endpoint with the URL you want to go to
            // The confirmation sets a cookie that allows access
            $client = $this->getHttpClient();

            // PopPorn redirects to /AgeConfirmation?url2=/ on first visit
            // We need to visit that page and follow through to set the etoken cookie

            // First, make a request that disables redirects to see where we're being sent
            try {
                $response = $client->get(self::BASE_URL . '/', [
                    'headers' => $this->getDefaultHeaders(),
                    'allow_redirects' => false,
                    'http_errors' => false,
                ]);

                // Check for redirect to age confirmation
                $statusCode = $response->getStatusCode();
                if ($statusCode === 302 || $statusCode === 301) {
                    $location = $response->getHeaderLine('Location');

                    // If redirected to AgeConfirmation, follow the flow
                    if (stripos($location, 'AgeConfirmation') !== false) {
                        // The redirect URL includes ?url2= parameter, we need to visit it
                        $ageConfirmUrl = $location;
                        if (!str_starts_with($ageConfirmUrl, 'http')) {
                            $ageConfirmUrl = self::BASE_URL . $ageConfirmUrl;
                        }

                        // Visit the age confirmation page
                        $ageResponse = $client->get($ageConfirmUrl, [
                            'headers' => $this->getDefaultHeaders(),
                            'allow_redirects' => true,
                            'http_errors' => false,
                        ]);

                        \Illuminate\Support\Facades\Log::debug('PopPorn age confirmation visited: ' . $ageConfirmUrl);
                    }
                }
            } catch (\Exception $e) {
                // Ignore redirect errors
            }

            // Wait a moment to simulate human behavior
            usleep(500000); // 500ms

        } catch (\Exception $e) {
            // Log but don't fail - we'll try the search anyway
            \Illuminate\Support\Facades\Log::debug('PopPorn age verification setup: ' . $e->getMessage());
        }
    }
}

