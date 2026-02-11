<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;

/**
 * Adult DVD Empire (ADE) provider pipe.
 *
 * Handles movie information extraction from adultdvdempire.com
 */
class AdePipe extends AbstractAdultProviderPipe
{
    protected int $priority = 40;

    private const BASE_URL = 'https://www.adultdvdempire.com';

    private const SEARCH_URL = '/dvd/search?q=';

    protected string $directUrl = '';

    protected string $title = '';

    protected string $response = '';

    public function getName(): string
    {
        return 'ade';
    }

    public function getDisplayName(): string
    {
        return 'Adult DVD Empire';
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

        if ($this->response === false) { // @phpstan-ignore identical.alwaysFalse
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

    /**
     * @return array<string, mixed>
     */
    protected function search(string $movie): array|false
    {
        if (empty($movie)) {
            return false;
        }

        // Initialize session with age verification cookies
        $this->initializeSession();

        $searchUrl = self::BASE_URL.self::SEARCH_URL.rawurlencode($movie);
        $response = $this->fetchHtml($searchUrl, $this->cookie);

        if ($response === false) {
            return false;
        }

        $this->getHtmlParser()->loadHtml($response);

        // Try multiple search result selectors
        $resultSelectors = [
            'a[class=fancybox-button]',
            'div.card a.boxcover-link',
            'a[href*="/item/"]',
        ];

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($resultSelectors as $selector) {
            $res = $this->getHtmlParser()->find($selector);
            if (! empty($res)) {
                foreach ($res as $ret) {
                    $title = $ret->title ?? $ret->getAttribute('title') ?? trim($ret->plaintext);
                    $url = trim($ret->href ?? '');

                    if (empty($title) || empty($url)) {
                        continue;
                    }

                    $similarity = $this->calculateSimilarity($movie, $title);

                    if ($similarity > $highestSimilarity) {
                        $highestSimilarity = $similarity;
                        $bestMatch = [
                            'title' => trim($title),
                            'url' => str_starts_with($url, 'http') ? $url : self::BASE_URL.$url,
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

        // Get all the movie data
        $results = array_merge($results, $this->extractSynopsis());
        $results = array_merge($results, $this->extractProductInfo(true));
        $results = array_merge($results, $this->extractCast());
        $results = array_merge($results, $this->extractGenres());
        $results = array_merge($results, $this->extractCovers());
        $results = array_merge($results, $this->extractTrailers());

        if (empty($results)) {
            return false;
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractTrailers(): array
    {
        $res = [];

        $trailersUrl = str_replace('/item/', '/item/trailers/', $this->directUrl);
        $trailersResponse = $this->fetchHtml($trailersUrl, $this->cookie);

        if ($trailersResponse !== false) {
            if (preg_match("/([\"|'])(?P<swf>[^\"']+.swf)([\"|'])/i", $trailersResponse, $hits)) {
                $res['trailers']['url'] = self::BASE_URL.trim(trim($hits['swf']), '"');

                if (preg_match('#(?:streamID:\s\")(?P<streamid>[0-9A-Z]+)(?:\")#', $trailersResponse, $hits)) {
                    $res['trailers']['streamid'] = trim($hits['streamid']);
                }

                if (preg_match('#(?:BaseStreamingUrl:\s\")(?P<baseurl>[\d]+\.[\d]+\.[\d]+\.[\d]+)(?:\")#', $trailersResponse, $hits)) {
                    $res['trailers']['baseurl'] = $hits['baseurl'];
                }
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

        // Try multiple selectors for better reliability
        $selectors = [
            'div#Boxcover img[itemprop=image]',
            'img[itemprop=image]',
            'div#Boxcover img',
            'div.boxcover img',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->getHtmlParser()->findOne($selector);
            if ($ret && isset($ret->src)) { // @phpstan-ignore booleanAnd.leftAlwaysTrue
                // Get high-resolution covers
                $res['boxcover'] = preg_replace('/[ms]\.jpg$/', 'h.jpg', $ret->src);
                $res['backcover'] = preg_replace('/[ms]\.jpg$/', 'bh.jpg', $ret->src);

                return $res;
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

        // Try multiple selectors in priority order
        $selectors = [
            'meta[property="og:description"]' => 'content',
            'meta[name="description"]' => 'content',
            'div[itemprop="description"]' => 'plaintext',
            'p.synopsis' => 'plaintext',
        ];

        foreach ($selectors as $selector => $property) {
            $meta = $this->getHtmlParser()->findOne($selector);
            if ($meta && isset($meta->$property) && $meta->$property !== false && ! empty(trim($meta->$property))) { // @phpstan-ignore booleanAnd.leftAlwaysTrue
                $res['synopsis'] = trim($meta->$property);

                return $res;
            }
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

        // Try multiple selector strategies
        $selectors = [
            'div[itemprop="actor"] span[itemprop="name"]',
            'div.performer-list a',
            'a[href*="/performer/"]',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (! empty($elements)) {
                foreach ($elements as $a) {
                    if ($a->plaintext !== false && ! empty(trim($a->plaintext))) {
                        $cast[] = trim($a->plaintext);
                    }
                }

                if (! empty($cast)) {
                    break;
                }
            }
        }

        $res['cast'] = array_values(array_unique($cast));

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractGenres(): array
    {
        $res = [];
        $genres = [];

        // Try multiple selector strategies
        $selectors = [
            'a[Label="Category"]',
            'div.categories a',
            'a[href*="/category/"]',
            'span[itemprop="genre"]',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->getHtmlParser()->find($selector);
            if (! empty($elements)) {
                foreach ($elements as $a) {
                    if ($a->plaintext !== false && ! empty(trim($a->plaintext))) {
                        $genres[] = trim($a->plaintext);
                    }
                }

                if (! empty($genres)) {
                    break;
                }
            }
        }

        $res['genres'] = array_values(array_unique($genres));

        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractProductInfo(bool $extras = false): array
    {
        $res = [];
        $dofeature = null;

        $tmpResponse = str_ireplace('Section ProductInfo', 'spdinfo', $this->response);
        $tmpHtml = new \voku\helper\HtmlDomParser;
        $tmpHtml->loadHtml($tmpResponse);

        if ($ret = $tmpHtml->findOne('div[class=spdinfo]')) { // @phpstan-ignore if.alwaysTrue
            $productinfo = [];
            $extrasData = [];

            $tmpResponse = trim($ret->outertext);
            $ret = $tmpHtml->loadHtml($tmpResponse);

            foreach ($ret->find('text') as $strong) {
                if (trim($strong->innertext) === 'Features') {
                    $dofeature = true;
                }
                if ($dofeature !== true) {
                    if (trim($strong->innertext) !== '&nbsp;') {
                        $productinfo[] = trim($strong->innertext);
                    }
                } else {
                    if ($extras === true) {
                        $extrasData[] = trim($strong->innertext);
                    }
                }
            }

            array_shift($productinfo);
            array_shift($productinfo);
            $res['productinfo'] = array_chunk($productinfo, 2, false);

            if (! empty($extrasData)) {
                $res['extras'] = $extrasData;
            }
        }

        return $res;
    }

    /**
     * Initialize session by visiting the site to establish cookies.
     * ADE uses JavaScript-based age verification with cookies.
     */
    protected function initializeSession(): void
    {
        try {
            $client = $this->getHttpClient();

            // Visit the homepage first to establish a session
            $client->get(self::BASE_URL, [
                'headers' => $this->getDefaultHeaders(),
                'allow_redirects' => true,
            ]);

            usleep(300000); // 300ms delay

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::debug('ADE session initialization: '.$e->getMessage());
        }
    }
}
