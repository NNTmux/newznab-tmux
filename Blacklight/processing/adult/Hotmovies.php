<?php

namespace Blacklight\processing\adult;

/**
 * Class Hotmovies - HotMovies.com scraper
 * Handles movie information extraction from hotmovies.com
 */
class Hotmovies extends AdultMovies
{
    /**
     * Constant Urls used within this class
     * Needed Search Queries Variables.
     */
    private const EXTRASEARCH = '&complete=on&search_in=video_title';

    private const HMURL = 'https://www.hotmovies.com';

    private const TRAILINGSEARCH = '/search.php?words=';

    /**
     * Keyword Search.
     */
    protected string $searchTerm = '';

    /**
     * Define a cookie location.
     */
    public string $cookie = '';

    /**
     * If a direct link is set parse it instead of search for it.
     */
    protected string $directLink = '';

    /**
     * Sets the direct url in the getAll method.
     */
    protected string $_directUrl = '';

    /**
     * Sets the link to get in curl.
     */
    protected string $_getLink = '';

    /**
     * POST parameters used with curl.
     */
    protected array $_postParams = [];

    /**
     * Results return from some methods.
     */
    protected array $_res = [];

    /**
     * Raw Html from Curl.
     */
    protected $_response;

    /**
     * Sets the title in the getAll method.
     */
    protected string $_title = '';

    /**
     * Minimum similarity threshold for matching
     */
    protected float $minimumSimilarity = 90.0;

    protected function trailers(): false
    {
        // TODO: Implement trailers() method.

        return false;
    }

    /**
     * Gets the synopsis.
     */
    protected function synopsis(): array
    {
        $this->_res['synopsis'] = 'N/A';

        // Try multiple selectors
        $selectors = [
            '.video_description',
            'div.description',
            'div.synopsis',
            'meta[name="description"]',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->_html->findOne($selector);
            if ($ret) {
                $text = $ret->innerText ?? $ret->plaintext ?? $ret->content ?? '';
                if (! empty(trim($text))) {
                    $this->_res['synopsis'] = trim($text);

                    return $this->_res;
                }
            }
        }

        return $this->_res;
    }

    /**
     * Process ProductInfo.
     */
    protected function productInfo(bool $extras = false): mixed
    {
        $studio = false;
        $director = false;
        if (($ret = $this->_html->find('div.page_video_info')) && ! empty($ret->find('text'))) {
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
                        if ($director === true && ! empty($e)) {
                            $this->_res['director'] = $e;
                            $e = null;
                            $director = false;
                        }
                        if (! empty($e)) {
                            $this->_res['productinfo'][] = $e;
                        }
                    } else {
                        break;
                    }
                }
            }
        }
        if (isset($this->_res['productinfo']) && \is_array($this->_res['productinfo'])) {
            $this->_res['productinfo'] = array_chunk($this->_res['productinfo'], 2, false);
        }

        return $this->_res;
    }

    /**
     * Gets the cast members and director.
     */
    protected function cast(): array
    {
        $cast = [];

        // Prefer scoped search within stars container to avoid unrelated links
        if ($container = $this->_html->findOne('.stars')) {
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
            foreach ($this->_html->find('a[href*="/performers/"]') as $e) {
                $name = trim($e->plaintext);
                if ($name !== '') {
                    $cast[] = $name;
                }
            }
        }

        if (! empty($cast)) {
            $this->_res['cast'] = array_values(array_unique($cast));
        }

        return $this->_res;
    }

    /**
     * Gets categories.
     */
    protected function genres(): array
    {
        $genres = [];
        if ($ret = $this->_html->findOne('div.categories')) {
            foreach ($ret->find('a') as $e) {
                if (str_contains($e->title, ' -> ')) {
                    $e = explode(' -> ', $e->plaintext);
                    $genres[] = trim($e[1]);
                }
            }
            $this->_res['genres'] = $genres;
        }

        return $this->_res;
    }

    /**
     * Get Box Cover Images.
     */
    protected function covers(): array|false
    {
        // Try multiple selectors
        $selectors = [
            'img#cover',
            'div#large_cover img',
            'img.boxcover',
            'div.product-image img',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->_html->findOne($selector);
            if ($ret && isset($ret->src)) {
                $this->_res['boxcover'] = trim($ret->src);
                $this->_res['backcover'] = str_ireplace(['.cover', 'front'], ['.back', 'back'], trim($ret->src));

                return $this->_res;
            }
        }

        return false;
    }

    /**
     * Searches for match against xxx movie name.
     *
     * @return bool , true if search >= 90%
     */
    public function processSite(string $movie): bool
    {
        if (empty($movie)) {
            return false;
        }

        $this->_getLink = self::HMURL.self::TRAILINGSEARCH.urlencode($movie).self::EXTRASEARCH;
        $this->_response = getRawHtml($this->_getLink, $this->cookie);

        if ($this->_response === false) {
            return false;
        }

        $this->_html->loadHtml($this->_response);

        // Try multiple result selectors
        $resultSelectors = [
            'h3[class=title] a[title]',
            'h3.title a',
            'div.movie-title a',
        ];

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($resultSelectors as $selector) {
            $elements = $this->_html->find($selector);
            if (! empty($elements)) {
                foreach ($elements as $ret) {
                    $title = $ret->title ?? $ret->plaintext ?? '';
                    $url = $ret->href ?? '';

                    if (empty($title) || empty($url)) {
                        continue;
                    }

                    // Clean title for better matching
                    $cleanTitle = str_replace('/XXX/', '', $title);
                    $cleanTitle = preg_replace('/\(.*?\)|[._-]/', ' ', $cleanTitle);
                    $cleanTitle = preg_replace('/\s+/', ' ', trim($cleanTitle));

                    similar_text(strtolower($movie), strtolower($cleanTitle), $p);

                    if ($p > $highestSimilarity) {
                        $highestSimilarity = $p;
                        $bestMatch = [
                            'title' => trim($title),
                            'url' => trim($url),
                        ];
                    }
                }

                // If we found results with this selector, don't try others
                if ($bestMatch !== null) {
                    break;
                }
            }
        }

        // Check if best match meets threshold
        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            $this->_title = $bestMatch['title'];
            $this->_getLink = $bestMatch['url'];
            $this->_directUrl = str_starts_with($bestMatch['url'], 'http')
                ? $bestMatch['url']
                : self::HMURL.$bestMatch['url'];

            unset($this->_response);
            $this->_response = getRawHtml($this->_directUrl, $this->cookie);

            if ($this->_response !== false) {
                $this->_html->loadHtml($this->_response);

                return true;
            }
        }

        return false;
    }
}
