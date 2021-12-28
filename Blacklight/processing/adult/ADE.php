<?php

namespace Blacklight\processing\adult;

/**
 * Class ADE.
 */
class ADE extends AdultMovies
{
    /**
     * If a direct link is given parse it rather then search.
     *
     * @var string
     */
    protected $directLink = '';

    /**
     * Search keyword.
     *
     * @var string
     */
    protected $searchTerm = '';

    /**
     * Define ADE Url here.
     */
    private const ADE = 'http://www.adultdvdempire.com';

    /**
     * Direct Url returned in getAll method.
     *
     * @var string
     */
    protected $_directUrl = '';

    /**
     * Sets the title in the getAll method.
     *
     * @var string
     */
    protected $_title = '';

    /** Trailing urls */
    protected $_dvdQuery = '/dvd/search?q=';
    protected $_scenes = '/scenes';
    protected $_boxCover = '/boxcover';
    protected $_backCover = '/backcover';
    protected $_reviews = '/reviews';
    protected $_trailers = '/trailers';

    protected $_url;
    protected $_response;
    protected $_res = [];
    protected $_tmpResponse;
    protected $_ch;

    /**
     * Gets Trailer Movies.
     *
     * @return array - url, streamid, basestreamingurl
     */
    protected function trailers()
    {
        $this->_response = getRawHtml(self::ADE.$this->_trailers.$this->_directUrl);
        $this->_html->loadHtml($this->_response);
        if (preg_match("/([\"|'])(?P<swf>[^\"']+.swf)([\"|'])/i", $this->_response, $hits)) {
            $this->_res['trailers']['url'] = self::ADE.trim(trim($hits['swf']), '"');
            if (preg_match(
                '#(?:streamID:\s\")(?P<streamid>[0-9A-Z]+)(?:\")#',
                $this->_response,
                $hits
            )
            ) {
                $this->_res['trailers']['streamid'] = trim($hits['streamid']);
            }
            if (preg_match(
                '#(?:BaseStreamingUrl:\s\")(?P<baseurl>[\d]+.[\d]+.[\d]+.[\d]+)(?:\")#',
                $this->_response,
                $hits
            )
            ) {
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
    protected function covers()
    {
        if ($ret = $this->_html->find('div#Boxcover, img[itemprop=image]', 1)) {
            $this->_res['boxcover'] = preg_replace('/m\.jpg/', 'h.jpg', $ret->src);
            $this->_res['backcover'] = preg_replace('/m\.jpg/', 'bh.jpg', $ret->src);
        }

        return $this->_res;
    }

    /**
     * Gets the synopsis.
     *
     * @return array - plot
     */
    protected function synopsis()
    {
        $ret = $this->_html->findOne('meta[name=og:description]')->content;
        if ($ret !== false) {
            $this->_res['synopsis'] = trim($ret);
        }

        return $this->_res;
    }

    /**
     * Gets the cast members and/or awards.
     *
     *
     * @return array - cast, awards
     */
    protected function cast(): array
    {
        $cast = [];
        foreach ($this->_html->find('h3') as $a) {
            if ($a->plaintext !== false) {
                $cast[] = trim($a->plaintext);
            }
        }
        $this->_res['cast'] = $cast;

        return $this->_res;
    }

    /**
     * Gets Genres, if exists return array else return false.
     *
     * @return mixed array - Genres
     */
    protected function genres()
    {
        $genres = [];
        foreach ($this->_html->find('[Label="Category"]') as $a) {
            if ($a->plaintext !== false) {
                $genres[] = trim($a->plaintext);
            }
        }
        $this->_res['genres'] = $genres;

        return $this->_res;
    }

    /**
     * Gets Product Information and/or Features.
     *
     * @param  bool  $extras
     * @return array - ProductInfo/Extras = features
     */
    protected function productInfo($extras = false)
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
     * @param  string  $movie
     * @return bool - True if releases has 90% match, else false
     */
    public function processSite($movie): bool
    {
        if (empty($movie)) {
            return false;
        }
        $this->_response = getRawHtml(self::ADE.$this->_dvdQuery.rawurlencode($movie));
        if ($this->_response !== false) {
            if ($res = $this->_html->loadHtml($this->_response)->find('a[class=fancybox-button]')) {
                foreach ($res as $ret) {
                    $title = $ret->title;
                    $title = str_replace('/XXX/', '', $title);
                    $title = preg_replace('/\(.*?\)|[._-]/', ' ', $title);
                    $url = trim($ret->href);
                    similar_text(strtolower($movie), strtolower($title), $p);
                    if ($p >= 90) {
                        $this->_directUrl = self::ADE.$url;
                        $this->_title = trim($title);
                        unset($this->_response);
                        $this->_response = getRawHtml($this->_directUrl);
                        $this->_html->loadHtml($this->_response);

                        return true;
                    }
                }

                return false;
            }

            return false;
        }

        return false;
    }
}
