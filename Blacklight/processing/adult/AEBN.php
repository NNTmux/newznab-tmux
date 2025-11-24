<?php

namespace Blacklight\processing\adult;

use voku\helper\SimpleHtmlDomNodeBlank;

/**
 * Class AEBN - AEBN Theater scraper
 * Handles movie information extraction from straight.theater.aebn.net
 */
class AEBN extends AdultMovies
{
    /**
     * Keyword to search.
     */
    public string $searchTerm = '';

    /**
     * Url Constants used within this class.
     */
    private const AEBNSURL = 'https://straight.theater.aebn.net';

    private const TRAILINGSEARCH = '/dispatcher/fts?theaterId=13992&genreId=101&locale=en&count=30&imageType=Large&targetSearchMode=basic&isAdvancedSearch=false&isFlushAdvancedSearchCriteria=false&sortType=Relevance&userQuery=title%3A+%2B';

    private const TRAILERURL = '/dispatcher/previewPlayer?locale=en&theaterId=13992&genreId=101&movieId=';

    /**
     * Direct Url in getAll method.
     */
    protected string $_directUrl = '';

    /**
     * Raw Html response from curl.
     */
    protected $_response;

    protected string $_trailerUrl = '';

    /**
     * Returned results in all methods except search/geturl.
     *
     * @var array
     */
    protected $_res = [
        'backcover' => [],
        'boxcover' => [],
        'cast' => [],
        'director' => [],
        'genres' => [],
        'productinfo' => [],
        'synopsis' => [],
        'trailers' => ['url' => []],
    ];

    /**
     * Sets title in getAll method.
     */
    protected string $_title = '';

    /**
     * Minimum similarity threshold for matching
     */
    protected float $minimumSimilarity = 90.0;

    public string $cookie = '';

    /**
     * Gets Trailer URL . will be processed in XXX insertswf.
     */
    protected function trailers(): mixed
    {
        $ret = $this->_html->find('a[itemprop=trailer]', 0);
        if (! empty($ret) && preg_match('/movieId=(?<movieid>\d+)&/', trim($ret->href), $hits)) {
            $movieid = $hits['movieid'];
            $this->_res['trailers']['url'] = self::AEBNSURL.self::TRAILERURL.$movieid;
        }

        return $this->_res;
    }

    /**
     * Gets the front and back cover of the box.
     */
    protected function covers(): array
    {
        // Try multiple selectors
        $selectors = [
            'img[itemprop=thumbnailUrl]',
            'div#md-boxCover img',
            'img.boxcover',
        ];

        foreach ($selectors as $selector) {
            $ret = $this->_html->findOne($selector);
            if ($ret && isset($ret->src)) {
                $coverUrl = trim($ret->src);

                // Ensure URL has protocol
                if (str_starts_with($coverUrl, '//')) {
                    $coverUrl = 'https:'.$coverUrl;
                }

                // Get high-resolution versions
                $this->_res['boxcover'] = str_ireplace(['160w.jpg', '120w.jpg'], 'xlf.jpg', $coverUrl);
                $this->_res['backcover'] = str_ireplace(['160w.jpg', '120w.jpg'], 'xlb.jpg', $coverUrl);

                return $this->_res;
            }
        }

        return $this->_res;
    }

    /**
     * Gets the Genres "Categories".
     */
    protected function genres(): array
    {
        if ($ret = $this->_html->findOne('div.md-detailsCategories')) {
            foreach ($ret->find('a[itemprop=genre]') as $genre) {
                $this->_res['genres'][] = trim($genre->plaintext);
            }
        }
        if (! empty($this->_res['genres'])) {
            $this->_res['genres'] = array_unique($this->_res['genres']);
        }

        return $this->_res;
    }

    /**
     * Gets the Cast Members "Stars" and Director if any.
     */
    protected function cast(): array
    {
        // Do not reset the whole results array; only populate the cast key.
        $cast = [];
        $ret = $this->_html->findOne('div.starsFull');
        if (! $ret instanceof SimpleHtmlDomNodeBlank) {
            foreach ($ret->find('span[itemprop=name]') as $star) {
                $cast[] = trim($star->plaintext);
            }
        } else {
            $ret = $this->_html->findOne('div.detailsLink');
            if (! $ret instanceof SimpleHtmlDomNodeBlank) {
                foreach ($ret->find('span') as $star) {
                    if (str_contains($star->plaintext, '/More/') && str_contains($star->plaintext, '/Stars/')) {
                        $cast[] = trim($star->plaintext);
                    }
                }
            }
        }
        if (! empty($cast)) {
            $this->_res['cast'] = $cast;
        }

        return $this->_res;
    }

    /**
     * Gets the product information.
     */
    protected function productInfo(bool $extras = false): mixed
    {
        if ($ret = $this->_html->find('div#md-detailsLeft', 0)) {
            foreach ($ret->find('div') as $div) {
                foreach ($div->find('span') as $span) {
                    $text = rawurldecode($span->plaintext);
                    $text = preg_replace('/&nbsp;/', '', $text);
                    $this->_res['productinfo'][] = trim($text);
                }
            }
            if (false !== $key = array_search('Running Time:', $this->_res['productinfo'], false)) {
                unset($this->_res['productinfo'][$key + 2]);
            }
            if (false !== $key = array_search('Director:', $this->_res['productinfo'], false)) {
                $this->_res['director'] = $this->_res['productinfo'][$key + 1];
                unset($this->_res['productinfo'][$key], $this->_res['productinfo'][$key + 1]);
            }
            $this->_res['productinfo'] = array_chunk($this->_res['productinfo'], 2, false);
        }

        return $this->_res;
    }

    /**
     * Gets the synopsis "plot".
     */
    protected function synopsis(): array
    {
        // Prefer the modern schema attribute
        $ret = $this->_html->findOne('span[itemprop=about]');
        if ($ret && $ret->plaintext !== null) {
            $this->_res['synopsis'] = trim($ret->plaintext);

            return $this->_res;
        }

        // Fallback to legacy description container
        $ret = $this->_html->findOne('div.movieDetailDescription');
        if ($ret && $ret->plaintext !== null) {
            $text = trim($ret->plaintext);
            $this->_res['synopsis'] = preg_replace('/^Description:\s*/', '', $text);
        }

        return $this->_res;
    }

    /**
     * Searches for a XXX name.
     */
    public function processSite(string $movie): bool
    {
        if (empty($movie)) {
            return false;
        }

        $this->_trailerUrl = self::TRAILINGSEARCH.urlencode($movie);
        $this->_response = getRawHtml(self::AEBNSURL.$this->_trailerUrl, $this->cookie);

        if ($this->_response === false) {
            return false;
        }

        $this->_html->loadHtml($this->_response);

        $bestMatch = null;
        $highestSimilarity = 0;
        $i = 1;

        foreach ($this->_html->find('div.movie') as $mov) {
            // Try multiple selector patterns
            $selectors = [
                'a#FTSMovieSearch_link_title_detail_'.$i,
                'a.title-link',
                'a[href*="/movie/"]',
            ];

            $ret = null;
            foreach ($selectors as $selector) {
                $ret = $mov->findOne($selector);
                if ($ret) {
                    break;
                }
            }

            if ($ret && isset($ret->href)) {
                $title = $ret->title ?? trim($ret->plaintext);

                if (! empty($title)) {
                    // Clean title for better matching
                    $cleanTitle = str_replace('/XXX/', '', $title);
                    $cleanTitle = preg_replace('/\(.*?\)|[._-]/', ' ', $cleanTitle);
                    $cleanTitle = preg_replace('/\s+/', ' ', trim($cleanTitle));

                    similar_text(strtolower($movie), strtolower($cleanTitle), $p);

                    if ($p > $highestSimilarity) {
                        $highestSimilarity = $p;
                        $bestMatch = [
                            'title' => trim($title),
                            'url' => html_entity_decode($ret->href),
                        ];
                    }
                }
            }
            $i++;
        }

        // Check if best match meets threshold
        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            $this->_title = $bestMatch['title'];
            $this->_trailerUrl = $bestMatch['url'];
            $this->_directUrl = self::AEBNSURL.$this->_trailerUrl;

            unset($this->_response);
            $this->_response = getRawHtml(self::AEBNSURL.$this->_trailerUrl, $this->cookie);

            if ($this->_response !== false) {
                $this->_html->loadHtml($this->_response);

                return true;
            }
        }

        return false;
    }
}
