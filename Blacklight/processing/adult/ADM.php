<?php

namespace Blacklight\processing\adult;

use voku\helper\SimpleHtmlDomNodeBlank;

class ADM extends AdultMovies
{
    /**
     * Override if 18 years+ or older
     * Define Adult DVD Marketplace url
     * Needed Search Queries Constant.
     */
    private const ADMURL = 'http://www.adultdvdmarketplace.com';

    private const TRAILINGSEARCH = '/xcart/adult_dvd/advanced_search.php?sort_by=relev&title=';

    /**
     * Define a cookie file location for curl.
     *
     * @var string string
     */
    public string $cookie = '';

    /**
     * Direct Link given from outside url doesn't do a search.
     *
     * @var string
     */
    protected string $directLink = '';

    /**
     * Set this for what you are searching for.
     *
     * @var string
     */
    protected string $searchTerm = '';

    /**
     * Sets the directurl for the return results array.
     *
     * @var string
     */
    protected string $_directUrl = '';

    /**
     * Results returned from each method.
     *
     * @var array
     */
    protected array $_res = [];

    /**
     * Curl Raw Html.
     */
    protected $_response;

    /**
     * Add this to popurl to get results.
     *
     * @var string
     */
    protected string $_trailUrl = '';

    /**
     * This is set in the getAll method.
     *
     * @var string
     */
    protected string $_title = '';

    /**
     * Get Box Cover Images.
     *
     * @return array - boxcover,backcover
     */
    protected function covers(): array
    {
        $baseUrl = 'http://www.adultdvdmarketplace.com/';
        if ($ret = $this->_html->find('a[rel=fancybox-button]', 0)) {
            if (isset($ret->href) && preg_match('/images\/.*[\d]+\.jpg$/i', $ret->href, $hits)) {
                $this->_res['boxcover'] = $baseUrl.$hits[0];
                $this->_res['backcover'] = $baseUrl.str_ireplace('/front/i', 'back', $hits[0]);
            }
        } elseif ($ret = $this->_html->find('img[rel=license]', 0)) {
            if (preg_match('/images\/.*[\d]+\.jpg$/i', $ret->src, $hits)) {
                $this->_res['boxcover'] = $baseUrl.$hits[0];
            }
        }

        return $this->_res;
    }

    /**
     * Gets the synopsis.
     */
    protected function synopsis(): array
    {
        $this->_res['synopsis'] = 'N/A';
        foreach ($this->_html->find('h3') as $heading) {
            if (trim($heading->plaintext) === 'Description') {
                $this->_res['synopsis'] = trim($heading->next_sibling()->plaintext);
            }
        }

        return $this->_res;
    }

    /**
     * Get Product Information and Director.
     */
    protected function productInfo(bool $extras = false): mixed
    {
        foreach ($this->_html->find('ul.list-unstyled li') as $li) {
            $category = explode(':', $li->plaintext);
            switch (trim($category[0])) {
                case 'Director':
                    $this->_res['director'] = trim($category[1]);
                    break;
                case 'Format':
                case 'Studio':
                case 'Released':
                case 'SKU':
                    $this->_res['productinfo'][trim($category[0])] = trim($category[1]);
            }
        }

        return $this->_res;
    }

    /**
     * Gets the cast members.
     */
    protected function cast(): array
    {
        $cast = [];
        foreach ($this->_html->find('h3') as $heading) {
            if (trim($heading->plaintext) === 'Cast') {
                foreach ($heading->nextSibling() as $next) {
                    if (! $next instanceof SimpleHtmlDomNodeBlank && $next->nodeName !== 'h3') {
                        $next = $next->nextSibling();
                    }
                    if (preg_match_all('/search_performerid/', $next->href, $hits)) {
                        $cast[] = trim($next->plaintext);
                    }
                }
            }
        }
        $this->_res['cast'] = array_unique($cast);

        return $this->_res;
    }

    /**
     * Gets categories.
     */
    protected function genres(): array
    {
        $genres = [];
        foreach ($this->_html->find('ul.list-unstyled') as $li) {
            $category = explode(':', $li->plaintext);
            if (trim($category[0]) === 'Category') {
                foreach (explode(',', $category[1]) as $genre) {
                    $genres[] = trim($genre);
                }
                $this->_res['genres'] = $genres;
            }
        }

        return $this->_res;
    }

    /**
     * Searches for match against searchterm.
     *
     * @return bool - true if search = 100%
     */
    public function processSite(string $movie): bool
    {
        $result = false;
        if (! empty($movie)) {
            $this->_trailUrl = self::TRAILINGSEARCH.urlencode($movie);
            $this->_response = getRawHtml(self::ADMURL.$this->_trailUrl, $this->cookie);
            if ($this->_response !== false) {
                $check = $this->_html->loadHtml($this->_response)->find('img[rel=license]');
                if (\count($check) > 0) {
                    foreach ($check as $ret) {
                        if (isset($ret->alt)) {
                            $title = trim($ret->alt, '"');
                            $title = str_replace('/XXX/', '', $title);
                            $comparetitle = preg_replace('/[\W]/', '', $title);
                            $comparesearch = preg_replace('/[\W]/', '', $movie);
                            similar_text($comparetitle, $comparesearch, $p);
                            if ($p >= 90 && preg_match('/\/(?<sku>\d+)\.jpg$/i', $ret->src, $hits)) {
                                $this->_title = trim($title);
                                $this->_trailUrl = '/dvd_view_'.$hits['sku'].'.html';
                                $this->_directUrl = self::ADMURL.$this->_trailUrl;
                                unset($this->_response);
                                $this->_response = getRawHtml($this->_directUrl, $this->cookie);
                                $this->_html->loadHtml($this->_response);
                                $result = true;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    protected function trailers(): mixed
    {
        // TODO: Implement trailers() method.

        return false;
    }
}
