<?php

namespace Blacklight\processing\adult;

use voku\helper\SimpleHtmlDomNodeBlank;

/**
 * Class ADM - Adult DVD Marketplace scraper
 * Handles movie information extraction from adultdvdmarketplace.com
 */
class ADM extends AdultMovies
{
    /**
     * Override if 18 years+ or older
     * Define Adult DVD Marketplace url
     * Needed Search Queries Constant.
     */
    private const ADMURL = 'https://www.adultdvdmarketplace.com';

    private const TRAILINGSEARCH = '/xcart/adult_dvd/advanced_search.php?sort_by=relev&title=';

    /**
     * Define a cookie file location for curl.
     *
     * @var string string
     */
    public string $cookie = '';

    /**
     * Direct Link given from outside url doesn't do a search.
     */
    protected string $directLink = '';

    /**
     * Set this for what you are searching for.
     */
    protected string $searchTerm = '';

    /**
     * Sets the directurl for the return results array.
     */
    protected string $_directUrl = '';

    /**
     * Results returned from each method.
     */
    protected array $_res = [];

    /**
     * Curl Raw Html.
     */
    protected $_response;

    /**
     * Add this to popurl to get results.
     */
    protected string $_trailUrl = '';

    /**
     * This is set in the getAll method.
     */
    protected string $_title = '';

    /**
     * Minimum similarity threshold for matching
     */
    protected float $minimumSimilarity = 90.0;

    /**
     * Get Box Cover Images.
     *
     * @return array - box cover,back cover
     */
    protected function covers(): array
    {
        $baseUrl = 'https://www.adultdvdmarketplace.com/';

        // Try fancybox link first
        if ($ret = $this->_html->findOne('a[rel=fancybox-button]')) {
            if (isset($ret->href) && preg_match('/images\/.*[\d]+\.jpg$/i', $ret->href, $hits)) {
                $this->_res['boxcover'] = str_starts_with($hits[0], 'http')
                    ? $hits[0]
                    : $baseUrl.$hits[0];
                $this->_res['backcover'] = str_ireplace('/front/', '/back/', $this->_res['boxcover']);

                return $this->_res;
            }
        }

        // Try license image
        if ($ret = $this->_html->findOne('img[rel=license]')) {
            if (isset($ret->src) && preg_match('/images\/.*[\d]+\.jpg$/i', $ret->src, $hits)) {
                $this->_res['boxcover'] = str_starts_with($hits[0], 'http')
                    ? $hits[0]
                    : $baseUrl.$hits[0];

                return $this->_res;
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

        // Try to find Description heading
        foreach ($this->_html->find('h3') as $heading) {
            if (trim($heading->plaintext) === 'Description') {
                $nextElement = $heading->next_sibling();
                if ($nextElement && ! empty(trim($nextElement->plaintext))) {
                    $this->_res['synopsis'] = trim($nextElement->plaintext);

                    return $this->_res;
                }
            }
        }

        // Fallback: Try meta description
        $meta = $this->_html->findOne('meta[name="description"]');
        if ($meta && isset($meta->content) && ! empty(trim($meta->content))) {
            $this->_res['synopsis'] = trim($meta->content);
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
     * Searches for match against search term.
     *
     * @return bool - true if search = 100%
     */
    public function processSite(string $movie): bool
    {
        if (empty($movie)) {
            return false;
        }

        $this->_trailUrl = self::TRAILINGSEARCH.urlencode($movie);
        $this->_response = getRawHtml(self::ADMURL.$this->_trailUrl, $this->cookie);

        if ($this->_response === false) {
            return false;
        }

        $this->_html->loadHtml($this->_response);
        $check = $this->_html->find('img[rel=license]');

        if (empty($check)) {
            return false;
        }

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($check as $ret) {
            if (! isset($ret->alt) || ! isset($ret->src)) {
                continue;
            }

            $title = trim($ret->alt, '"');
            $title = str_replace('/XXX/', '', $title);
            $comparetitle = preg_replace('/[\W]/', '', strtolower($title));
            $comparesearch = preg_replace('/[\W]/', '', strtolower($movie));

            similar_text($comparetitle, $comparesearch, $p);

            if ($p > $highestSimilarity && preg_match('/\/(?<sku>\d+)\.jpg$/i', $ret->src, $hits)) {
                $highestSimilarity = $p;
                $bestMatch = [
                    'title' => trim($title),
                    'sku' => $hits['sku'],
                ];
            }
        }

        // Check if best match meets threshold
        if ($bestMatch !== null && $highestSimilarity >= $this->minimumSimilarity) {
            $this->_title = $bestMatch['title'];
            $this->_trailUrl = '/dvd_view_'.$bestMatch['sku'].'.html';
            $this->_directUrl = self::ADMURL.$this->_trailUrl;

            unset($this->_response);
            $this->_response = getRawHtml($this->_directUrl, $this->cookie);

            if ($this->_response !== false) {
                $this->_html->loadHtml($this->_response);

                return true;
            }
        }

        return false;
    }

    protected function trailers(): mixed
    {
        // TODO: Implement trailers() method.

        return false;
    }
}
