<?php

namespace Blacklight\processing\adult;

/**
 * Class ADE - Adult DVD Empire scraper
 * Handles movie information extraction from adultdvdempire.com
 */
class ADE extends AdultMovies
{
    /**
     * If a direct link is given parse it rather than search.
     */
    protected string $directLink = '';

    /**
     * Search keyword.
     */
    protected string $searchTerm = '';

    /**
     * Define ADE Url here.
     */
    private const ADE = 'https://www.adultdvdempire.com';

    /**
     * Direct Url returned in getAll method.
     */
    protected string $_directUrl = '';

    /**
     * Sets the title in the getAll method.
     */
    protected string $_title = '';

    /** Trailing urls */
    protected string $_dvdQuery = '/dvd/search?q=';

    protected string $_scenes = '/scenes';

    protected string $_boxCover = '/boxcover';

    protected string $_backCover = '/backcover';

    protected string $_reviews = '/reviews';

    protected string $_trailers = '/trailers';

    protected $_response;

    protected array $_res = [];

    protected $_tmpResponse;

    /**
     * Minimum similarity threshold for matching
     */
    protected float $minimumSimilarity = 90.0;

    /**
     * Gets Trailer Movies.
     *
     * @return array - url, streamid, basestreamingurl
     */
    protected function trailers(): array
    {
        $this->_response = getRawHtml(self::ADE.$this->_trailers.$this->_directUrl);
        $this->_html->loadHtml($this->_response);
        if (preg_match("/([\"|'])(?P<swf>[^\"']+.swf)([\"|'])/i", $this->_response, $hits)) {
            $this->_res['trailers']['url'] = self::ADE.trim(trim($hits['swf']), '"');
            if (preg_match(
                '#(?:streamID:\s\")(?P<streamid>[0-9A-Z]+)(?:\")#',
                $this->_response,
                $hits
            )) {
                $this->_res['trailers']['streamid'] = trim($hits['streamid']);
            }
            if (preg_match(
                '#(?:BaseStreamingUrl:\s\")(?P<baseurl>[\d]+\.[\d]+\.[\d]+\.[\d]+)(?:\")#',
                $this->_response,
                $hits
            )) {
                $this->_res['trailers']['baseurl'] = $hits['baseurl'];
            }
        }

        return $this->_res;
    }

    /**
     * Gets cover images for the xxx release.
     *
     * @return array - Boxcover and backcover
     */
    protected function covers(): array
    {
        // Try multiple selectors for better reliability
        $selectors = [
            'div#Boxcover img[itemprop=image]',
            'img[itemprop=image]',
            'div#Boxcover img',
            'div.boxcover img',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->_html->findOne($selector);
            if ($ret && isset($ret->src)) {
                // Get high-resolution covers
                $this->_res['boxcover'] = preg_replace('/[ms]\.jpg$/', 'h.jpg', $ret->src);
                $this->_res['backcover'] = preg_replace('/[ms]\.jpg$/', 'bh.jpg', $ret->src);

                return $this->_res;
            }
        }

        return $this->_res;
    }

    /**
     * Gets the synopsis.
     *
     * @return array - plot
     */
    protected function synopsis(): array
    {
        // Try multiple selectors in priority order
        $selectors = [
            'meta[property="og:description"]' => 'content',
            'meta[name="description"]' => 'content',
            'div[itemprop="description"]' => 'plaintext',
            'p.synopsis' => 'plaintext',
        ];

        foreach ($selectors as $selector => $property) {
            $meta = $this->_html->findOne($selector);
            if ($meta && isset($meta->$property) && $meta->$property !== false && ! empty(trim($meta->$property))) {
                $this->_res['synopsis'] = trim($meta->$property);

                return $this->_res;
            }
        }

        return $this->_res;
    }

    /**
     * Gets the cast members and/or awards.
     *
     * @return array - cast, awards
     */
    protected function cast(): array
    {
        $cast = [];

        // Try multiple selector strategies
        $selectors = [
            'div[itemprop="actor"] span[itemprop="name"]',
            'div.performer-list a',
            'a[href*="/performer/"]',
            'h3',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->_html->find($selector);
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

        $this->_res['cast'] = array_values(array_unique($cast));

        return $this->_res;
    }

    /**
     * Gets Genres, if exists return array else return false.
     *
     * @return mixed - Genres
     */
    protected function genres(): mixed
    {
        $genres = [];

        // Try multiple selector strategies
        $selectors = [
            'a[Label="Category"]',
            'div.categories a',
            'a[href*="/category/"]',
            'span[itemprop="genre"]',
        ];

        foreach ($selectors as $selector) {
            $elements = $this->_html->find($selector);
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

        $this->_res['genres'] = array_values(array_unique($genres));

        return $this->_res;
    }

    protected function productInfo(bool $extras = false): mixed
    {
        $dofeature = null;
        $this->_tmpResponse = str_ireplace('Section ProductInfo', 'spdinfo', $this->_response);
        $this->_html->loadHtml($this->_tmpResponse);
        if ($ret = $this->_html->findOne('div[class=spdinfo]')) {
            $this->_tmpResponse = trim($ret->outertext);
            $ret = $this->_html->loadHtml($this->_tmpResponse);
            foreach ($ret->find('text') as $strong) {
                if (trim($strong->innertext) === 'Features') {
                    $dofeature = true;
                }
                if ($dofeature !== true) {
                    if (trim($strong->innertext) !== '&nbsp;') {
                        $this->_res['productinfo'][] = trim($strong->innertext);
                    }
                } else {
                    if ($extras === true) {
                        $this->_res['extras'][] = trim($strong->innertext);
                    }
                }
            }

            array_shift($this->_res['productinfo']);
            array_shift($this->_res['productinfo']);
            $this->_res['productinfo'] = array_chunk($this->_res['productinfo'], 2, false);
        }

        return $this->_res;
    }

    /**
     * Searches xxx name.
     *
     * @return bool - True if releases has 90% match, else false
     */
    public function processSite(string $movie): bool
    {
        if (empty($movie)) {
            return false;
        }

        $this->_response = getRawHtml(self::ADE.$this->_dvdQuery.rawurlencode($movie));

        if ($this->_response === false) {
            return false;
        }

        $this->_html->loadHtml($this->_response);

        // Try multiple search result selectors
        $resultSelectors = [
            'a[class=fancybox-button]',
            'div.card a.boxcover-link',
            'a[href*="/item/"]',
        ];

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($resultSelectors as $selector) {
            $res = $this->_html->find($selector);
            if (! empty($res)) {
                foreach ($res as $ret) {
                    $title = $ret->title ?? $ret->getAttribute('title') ?? trim($ret->plaintext);
                    $url = trim($ret->href ?? '');

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
                            'url' => $url,
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
            $this->_directUrl = str_starts_with($bestMatch['url'], 'http')
                ? $bestMatch['url']
                : self::ADE.$bestMatch['url'];
            $this->_title = $bestMatch['title'];

            unset($this->_response);
            $this->_response = getRawHtml($this->_directUrl);

            if ($this->_response !== false) {
                $this->_html->loadHtml($this->_response);

                return true;
            }
        }

        return false;
    }
}
