<?php

namespace Blacklight\processing\adult;

use voku\helper\SimpleHtmlDomNodeBlank;

class AEBN extends AdultMovies
{
    /**
     * Keyword to search.
     *
     * @var string
     */
    public $searchTerm = '';

    /**
     * Url Constants used within this class.
     */
    private const AEBNSURL = 'http://straight.theater.aebn.net';

    private const TRAILINGSEARCH = '/dispatcher/fts?theaterId=13992&genreId=101&locale=en&count=30&imageType=Large&targetSearchMode=basic&isAdvancedSearch=false&isFlushAdvancedSearchCriteria=false&sortType=Relevance&userQuery=title%3A+%2B';

    private const TRAILERURL = '/dispatcher/previewPlayer?locale=en&theaterId=13992&genreId=101&movieId=';

    /**
     * Direct Url in getAll method.
     *
     * @var string
     */
    protected string $_directUrl = '';

    /**
     * Raw Html response from curl.
     */
    protected $_response;

    /**
     * @var string
     */
    protected $_trailerUrl = '';

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
     *
     * @var string
     */
    protected string $_title = '';

    /**
     * Gets Trailer URL .. will be processed in XXX insertswf.
     *
     *
     * @return mixed
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
        $ret = $this->_html->find('div#md-boxCover, img[itemprop=thumbnailUrl]', 1);
        if ($ret !== false) {
            $ret = trim($ret->src);
            if (strpos($ret, '//') === 0) {
                $ret = 'https:'.$ret;
            }
            $this->_res['boxcover'] = str_ireplace('160w.jpg', 'xlf.jpg', $ret);
            $this->_res['backcover'] = str_ireplace('160w.jpg', 'xlb.jpg', $ret);
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
        $this->_res = [];
        $ret = $this->_html->findOne('div.starsFull');
        if (! $ret instanceof SimpleHtmlDomNodeBlank) {
            foreach ($ret->find('span[itemprop=name]') as $star) {
                $this->_res['cast'][] = trim($star->plaintext);
            }
        } else {
            $ret = $this->_html->findOne('div.detailsLink');
            if (! $ret instanceof SimpleHtmlDomNodeBlank) {
                foreach ($ret->find('span') as $star) {
                    if (strpos($star->plaintext, '/More/') !== false && strpos($star->plaintext, '/Stars/') !== false) {
                        $this->_res['cast'][] = trim($star->plaintext);
                    }
                }
            }
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
        if ($ret = $this->_html->findOne('span[itemprop=about]')) {
            if ($ret === null) {
                if ($ret = $this->_html->findOne('div.movieDetailDescription')) {
                    $this->_res['synopsis'] = preg_replace('/Description:\s/', '', $this->_res['plot']);
                }
            } else {
                $this->_res['synopsis'] = trim($ret->plaintext);
            }
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
        if ($this->_response !== false) {
            $i = 1;
            foreach ($this->_html->loadHtml($this->_response)->find('div.movie') as $mov) {
                $string = 'a#FTSMovieSearch_link_title_detail_'.$i;
                if ($ret = $mov->findOne($string)) {
                    $title = str_replace('/XXX/', '', $ret->title);
                    $title = trim(preg_replace('/\(.*?\)|[._-]/', ' ', $title));
                    similar_text(strtolower($movie), strtolower($title), $p);
                    if ($p >= 90) {
                        $this->_title = trim($ret->title);
                        $this->_trailerUrl = html_entity_decode($ret->href);
                        $this->_directUrl = self::AEBNSURL.$this->_trailerUrl;
                        unset($this->_response);
                        $this->_response = getRawHtml(self::AEBNSURL.$this->_trailerUrl, $this->cookie);
                        $this->_html->loadHtml($this->_response);

                        return true;
                    }

                    continue;
                }
                $i++;
            }
        }

        return false;
    }
}
