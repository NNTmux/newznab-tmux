<?php

namespace Blacklight\processing\adult;

class Hotmovies extends AdultMovies
{
    /**
     * Constant Urls used within this class
     * Needed Search Queries Variables.
     */
    private const EXTRASEARCH = '&complete=on&search_in=video_title';

    private const HMURL = 'http://www.hotmovies.com';

    private const TRAILINGSEARCH = '/search.php?words=';

    /**
     * Keyword Search.
     *
     * @var string
     */
    protected $searchTerm = '';

    /**
     * Define a cookie location.
     *
     * @var string
     */
    public $cookie = '';

    /**
     * If a direct link is set parse it instead of search for it.
     *
     * @var string
     */
    protected $directLink = '';

    /**
     * Sets the direct url in the getAll method.
     */
    protected string $_directUrl = '';

    /**
     * Sets the link to get in curl.
     *
     * @var string
     */
    protected $_getLink = '';

    /**
     * POST parameters used with curl.
     *
     * @var array
     */
    protected $_postParams = [];

    /**
     * Results return from some methods.
     *
     * @var array
     */
    protected $_res = [];

    /**
     * Raw Html from Curl.
     */
    protected $_response;

    /**
     * Sets the title in the getAll method.
     */
    protected string $_title = '';

    protected function trailers(): mixed
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
        if ($this->_html->findOne('.desc_link')) {
            $ret = $this->_html->findOne('.video_description');
            if ($ret !== false) {
                $this->_res['synopsis'] = trim($ret->innerText);
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
                if (strpos($e, 'Director:') !== false) {
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
        if ($this->_html->find('.stars bottom_margin')) {
            foreach ($this->_html->find('a[title]') as $e) {
                $e = trim($e->title);
                $e = preg_replace('/\((.*)\)/', '', $e);
                $cast[] = trim($e);
            }
            $this->_res['cast'] = $cast;
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
    protected function covers(): mixed
    {
        if ($ret = $this->_html->find('div#large_cover, img#cover', 1)) {
            $this->_res['boxcover'] = trim($ret->src);
            $this->_res['backcover'] = str_ireplace('.cover', '.back', trim($ret->src));
        } else {
            return false;
        }

        return $this->_res;
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
        $this->_response = false;
        $this->_getLink = self::HMURL.self::TRAILINGSEARCH.urlencode($movie).self::EXTRASEARCH;
        $this->_response = getRawHtml($this->_getLink, $this->cookie);
        if ($this->_response !== false) {
            if ($ret = $this->_html->loadHtml($this->_response)->findOne('h3[class=title]')) {
                if ($ret->findOne('a[title]')) {
                    $ret = $ret->findOne('a[title]');
                    $title = trim($ret->title);
                    $title = str_replace('/XXX/', '', $title);
                    $title = preg_replace('/\(.*?\)|[._-]/', ' ', $title);
                    if (! empty($title)) {
                        similar_text($movie, $title, $p);
                        if ($p >= 90) {
                            $this->_title = $title;
                            $this->_getLink = trim($ret->href);
                            $this->_directUrl = trim($ret->href);
                            unset($this->_response);
                            if ($this->_getLink !== false) {
                                $this->_response = getRawHtml($this->_getLink, $this->cookie);
                                $this->_html->loadHtml($this->_response);
                            } else {
                                $this->_response = getRawHtml($this->_directUrl, $this->cookie);
                                $this->_html->loadHtml($this->_response);
                            }

                            return true;
                        }
                    }
                }
            }
        } else {
            return false;
        }

        return false;
    }
}
