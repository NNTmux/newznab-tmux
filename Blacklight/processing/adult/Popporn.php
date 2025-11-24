<?php

namespace Blacklight\processing\adult;

class Popporn extends AdultMovies
{
    /**
     * Define a cookie file location for curl.
     */
    public string $cookie = '';

    /**
     * Base URL for the site
     */
    private const BASE_URL = 'https://www.popporn.com';

    /**
     * Search endpoint
     */
    private const SEARCH_ENDPOINT = '/search?q=';

    /**
     * Age verification URL
     */
    private const AGE_VERIFICATION_URL = 'https://www.popporn.com/popporn/4';

    /**
     * Minimum similarity percentage to consider a match
     */
    protected float $minimumSimilarity = 90.0;

    /**
     * Direct URL for the movie
     */
    protected string $_directUrl = '';

    /**
     * Raw HTML response
     */
    protected $_response;

    /**
     * Results array
     */
    protected array $_res = [];

    /**
     * Movie title
     */
    protected string $_title = '';

    /**
     * Temporary URL for internal operations
     */
    protected string $_trailUrl = '';

    /**
     * POST parameters for API requests
     */
    private $_postParams;

    /**
     * Get Box Cover Images.
     */
    protected function covers(): array|false
    {
        // Method 1: Try structured data
        if (preg_match('/"image":\s*"(.*?)"/is', $this->_response, $match)) {
            $this->_res['boxcover'] = trim($match[1]);
            // Try to determine backcover from boxcover pattern
            if (stripos(trim($match[1]), '_aa') !== false) {
                $this->_res['backcover'] = str_ireplace('_aa', '_bb', trim($match[1]));
            } else {
                $this->_res['backcover'] = str_ireplace('.jpg', '_b.jpg', trim($match[1]));
            }

            return $this->_res;
        }

        // Method 2: Try multiple selectors
        $selectors = [
            'div[id=box-art], a[rel=box-art]',
            'img.front',
            'div.box-cover img',
            'div.product-image img',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->_html->findOne($selector)) {
                $this->_res['boxcover'] = $ret->href ?? $ret->src;

                // Try to determine backcover
                if (stripos($this->_res['boxcover'], '_aa') !== false) {
                    $this->_res['backcover'] = str_ireplace('_aa', '_bb', $this->_res['boxcover']);
                } else {
                    $this->_res['backcover'] = str_ireplace('.jpg', '_b.jpg', $this->_res['boxcover']);
                }

                // Also check for explicit back cover
                if (! isset($this->_res['backcover']) && $back = $this->_html->findOne('img.back')) {
                    $this->_res['backcover'] = $back->src;
                }

                return $this->_res;
            }
        }

        return false;
    }

    /**
     * Gets the movie synopsis/description
     */
    protected function synopsis(): array
    {
        // Method 1: Try structured data
        if (preg_match('/"description":\s*"(.*?)"/is', $this->_response, $match)) {
            $this->_res['synopsis'] = trim(html_entity_decode(str_replace('\\u', '\\u', $match[1])));

            return $this->_res;
        }

        // Method 2: Try multiple selectors
        $selectors = [
            'div[id=product-info] h3[class=highlight] + *',
            'div.product-description',
            'div.synopsis',
            'meta[name="description"]',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->_html->findOne($selector)) {
                $text = $ret->plaintext ?? $ret->content;

                // Filter out "POPPORN EXCLUSIVE" text
                if (stripos(trim($text), 'POPPORN EXCLUSIVE') !== false) {
                    if ($ret->next_sibling()) {
                        $text = trim($ret->next_sibling()->plaintext);
                    }
                }

                if (! empty($text)) {
                    $this->_res['synopsis'] = trim($text);

                    return $this->_res;
                }
            }
        }

        // Original method as fallback
        if ($ret = $this->_html->find('div[id=product-info] ,h3[class=highlight]', 1)) {
            if ($ret->next_sibling() && $ret->next_sibling()->plaintext) {
                if (stripos(trim($ret->next_sibling()->plaintext), 'POPPORN EXCLUSIVE') === false) {
                    $this->_res['synopsis'] = trim($ret->next_sibling()->plaintext);
                } else {
                    if ($ret->next_sibling()->next_sibling()) {
                        $this->_res['synopsis'] = trim($ret->next_sibling()->next_sibling()->next_sibling()->plaintext);
                    } else {
                        $this->_res['synopsis'] = 'N/A';
                    }
                }
            }
        }

        return $this->_res;
    }

    /**
     * Gets movie trailers
     */
    protected function trailers(): array
    {
        // Method 1: Try structured data
        if (preg_match('/"contentUrl":\s*"(.*?)"/is', $this->_response, $match)) {
            $url = trim($match[1]);
            if (! empty($url)) {
                $this->_res['trailers']['url'] = $url;

                return $this->_res;
            }
        }

        // Method 2: Modern video embeds
        $videoSelectors = [
            'video source',
            'iframe[src*="trailer"]',
            'video[src]',
        ];

        foreach ($videoSelectors as $selector) {
            $ret = $this->_html->findOne($selector);
            if ($ret && isset($ret->src) && ! empty(trim($ret->src))) {
                $this->_res['trailers']['url'] = trim($ret->src);

                return $this->_res;
            }
        }

        // Method 3: Original flash-based trailer extraction
        $ret = $this->_html->findOne('input#thickbox-trailer-link');
        if ($ret && \property_exists($ret, 'value')) {
            $val = trim((string) $ret->value);
            if (! empty($val)) {
                $val = str_replace('..', '', $val);
                $tmprsp = $this->_response;
                $this->_trailUrl = $val;

                if (preg_match_all('/productID="\+(?<id>\d+),/', $this->_response, $hits)) {
                    $productid = $hits['id'][0];
                    $random = ((float) mt_rand() / (float) mt_getrandmax()) * 5400000000000000;
                    $this->_trailUrl = '/com/tlavideo/vod/FlvAjaxSupportService.cfc?random='.$random;
                    $this->_postParams = 'method=pipeStreamLoc&productID='.$productid;

                    $response = getRawHtml(self::BASE_URL.$this->_trailUrl, $this->cookie, $this->_postParams);

                    if (! empty($response)) {
                        $retJson = json_decode(json_decode($response, true), true);
                        if ($retJson && isset($retJson['LOC']) && ! empty($retJson['LOC'])) {
                            $this->_res['trailers']['baseurl'] = self::BASE_URL.'/flashmediaserver/trailerPlayer.swf';
                            $this->_res['trailers']['flashvars'] = 'subscribe=false&image=&file='.self::BASE_URL.'/'.$retJson['LOC'].'&autostart=false';
                            // Also provide a modern URL if possible
                            $this->_res['trailers']['url'] = self::BASE_URL.'/'.$retJson['LOC'];
                        }
                    }

                    $this->_response = $tmprsp;
                }
            }
        }

        return $this->_res;
    }

    /**
     * Gets product information
     */
    protected function productInfo(bool $extras = false): array
    {
        $productInfo = [];
        $director = '';

        // Method 1: Try structured data
        if (preg_match('/"director":\s*{[^}]*"name":\s*"(.*?)"/is', $this->_response, $match)) {
            $director = trim($match[1]);
        }

        // Method 2: Look for product details in various formats
        $selectors = [
            'div#lside',
            'div.product-details',
            'div.product-info',
        ];

        foreach ($selectors as $selector) {
            if ($ret = $this->_html->findOne($selector)) {
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
                            if (! empty($e)) {
                                $rawInfo[] = $e;
                            }
                        } else {
                            break;
                        }
                    }
                }

                if (! empty($rawInfo)) {
                    $productInfo = array_chunk($rawInfo, 2, false);
                    break;
                }
            }
        }

        $this->_res['productinfo'] = $productInfo;
        $this->_res['director'] = $director;

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
                if ($ret = $this->_html->findOne($selector)) {
                    foreach ($ret->find('li') as $e) {
                        $text = trim($e->plaintext);
                        if ($text === 'Features:') {
                            $features = true;

                            continue;
                        }

                        if ($features === true && ! empty($text)) {
                            $extrasData[] = $text;
                        }
                    }

                    if (! empty($extrasData)) {
                        $this->_res['extras'] = $extrasData;
                        break;
                    }
                }
            }
        }

        return $this->_res;
    }

    /**
     * Gets the cast members
     */
    protected function cast(): array
    {
        $cast = [];
        $director = '';

        // Method 1: Try structured data
        if (preg_match_all('/"actor":\s*{[^}]*"name":\s*"(.*?)"/is', $this->_response, $matches)) {
            foreach ($matches[1] as $actor) {
                $cast[] = trim($actor);
            }
        }

        if (preg_match('/"director":\s*{[^}]*"name":\s*"(.*?)"/is', $this->_response, $match)) {
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
                $elements = $this->_html->find($selector);
                if (! empty($elements)) {
                    foreach ($elements as $element) {
                        $cast[] = trim($element->plaintext);
                    }
                    break;
                }
            }
        }

        // Method 3: Original method (fallback)
        if (empty($cast)) {
            $castFound = false;
            $directorFound = false;
            $rawCast = [];

            if ($ret = $this->_html->findOne('div#lside')) {
                foreach ($ret->find('text') as $e) {
                    $e = trim($e->innertext);
                    $e = str_replace([',', '&nbsp;'], '', $e);

                    if (stripos($e, 'Cast') !== false) {
                        $castFound = true;

                        continue;
                    }

                    $e = str_replace('Cast:', '', $e);

                    if ($castFound === true) {
                        if (stripos($e, 'Director:') !== false) {
                            $directorFound = true;

                            continue;
                        }

                        if ($directorFound === true && ! empty($e)) {
                            $director = $e;
                            $directorFound = false;

                            continue;
                        }

                        if (stripos($e, 'Country:') === false && ! empty($e)) {
                            $rawCast[] = $e;
                        } else {
                            break;
                        }
                    }
                }

                $cast = $rawCast;
            }
        }

        $this->_res['cast'] = array_unique(array_filter($cast));
        $this->_res['director'] = $director;

        return $this->_res;
    }

    /**
     * Gets categories/genres
     */
    protected function genres(): array
    {
        $genres = [];

        // Method 1: Try structured data
        if (preg_match_all('/"genre":\s*"(.*?)"/is', $this->_response, $matches)) {
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
                $elements = $this->_html->find($selector);
                if (! empty($elements)) {
                    foreach ($elements as $e) {
                        $genres[] = trim($e->plaintext);
                    }
                    break;
                }
            }
        }

        $this->_res['genres'] = array_unique(array_filter($genres));

        return $this->_res;
    }

    /**
     * Searches for match against search term.
     */
    public function processSite(string $movie): bool
    {
        if (empty($movie)) {
            return false;
        }

        $searchUrl = self::BASE_URL.self::SEARCH_ENDPOINT.urlencode($movie);
        $this->_response = getRawHtml($searchUrl, $this->cookie);

        if (empty($this->_response)) {
            // Try age verification URL
            $this->_response = getRawHtml(self::AGE_VERIFICATION_URL, $this->cookie);
            if (! empty($this->_response)) {
                $this->_html->loadHtml($this->_response);

                return false; // Need to verify age first
            }

            return false;
        }

        $this->_html->loadHtml($this->_response);
        $bestMatch = null;
        $highestSimilarity = 0;

        // Try multiple selector patterns for search results
        $resultSelectors = [
            'div.product-info a, div.title a',
            'div.product-title a',
            'h3.product-title a',
        ];

        foreach ($resultSelectors as $selector) {
            $results = $this->_html->find($selector);

            if (! empty($results)) {
                foreach ($results as $result) {
                    $title = $result->title ?? $result->plaintext;
                    $url = $result->href;

                    if (! empty($title)) {
                        // Clean title for better comparison
                        $cleanTitle = str_replace('XXX', '', $title);
                        $cleanTitle = preg_replace('/\(.*?\)|[._\-]/i', ' ', $cleanTitle);
                        $cleanTitle = trim($cleanTitle);

                        // Compare titles
                        similar_text(strtolower($movie), strtolower($cleanTitle), $similarity);

                        if ($similarity > $highestSimilarity) {
                            $highestSimilarity = $similarity;
                            $bestMatch = [
                                'title' => $title,
                                'url' => $url,
                            ];
                        }
                    }
                }

                break; // If we found results with this selector, no need to try others
            }
        }

        // If we found a match above our threshold
        if ($bestMatch && $highestSimilarity >= $this->minimumSimilarity) {
            $this->_title = trim($bestMatch['title']);
            $this->_directUrl = str_starts_with($bestMatch['url'], 'http')
                ? $bestMatch['url']
                : self::BASE_URL.$bestMatch['url'];

            // Fetch the movie details page
            $this->_response = getRawHtml($this->_directUrl, $this->cookie);
            if (! empty($this->_response)) {
                $this->_html->loadHtml($this->_response);

                return true;
            }
        }

        return false;
    }
}
