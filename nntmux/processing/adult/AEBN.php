<?php
namespace nntmux;

use nntmux\processing\adult\AdultMovies;

class AEBN extends AdultMovies
{
	/**
	 * Keyword to search
	 *
	 * @var string
	 */
	public $searchTerm = '';

	/**
	 * Url Constants used within this class
	 */
	const AEBNSURL = 'http://straight.theater.aebn.net';
	const IF18 = 'http://straight.theater.aebn.net/dispatcher/frontDoor?genreId=101&theaterId=13992&locale=en&refid=AEBN-000001';
	const TRAILINGSEARCH = '/dispatcher/fts?theaterId=13992&genreId=101&locale=en&count=30&imageType=Large&targetSearchMode=basic&isAdvancedSearch=false&isFlushAdvancedSearchCriteria=false&sortType=Relevance&userQuery=title%3A+%2B';
	const TRAILERURL = '/dispatcher/previewPlayer?locale=en&theaterId=13992&genreId=101&movieId=';

	/**
	 * Direct Url in getAll method
	 * @var string
	 */
	protected $_directUrl = '';

	/**
	 * Simple Html Dom Object
	 * @var \simple_html_dom
	 */
	protected $_html;

	/**
	 * Raw Html response from curl
	 *
	 */
	protected $_response;

	/**
	 * @var string
	 */
	protected $_trailerUrl = '';

	/**
	 * Returned results in all methods except search/geturl
	 *
	 * @var array
	 */
	protected static $res = [
		'backcover'		=> [],
		'boxcover'		=> [],
		'cast'			=> [],
		'director'		=> [],
		'genres'		=> [],
		'productinfo'	=> [],
		'synopsis'		=> [],
		'trailers'		=> ['url' =>[]],
	];

	/**
	 * If searchTerm is found
	 * @var bool
	 */
	protected $_searchFound = false;

	/**
	 * Sets title in getAll method
	 * @var string
	 */
	protected $_title = '';


	/**
	 * Sets the variables that used throughout the class
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->_html = new \simple_html_dom();
	}

	/**
	 * Gets Trailer URL .. will be processed in XXX insertswf
	 *
	 * @return array|bool
	 */
	protected function trailers()
	{
		$ret = $this->_html->find('a[itemprop=trailer]', 0);
		if (preg_match('/movieId=(?<movieid>\d+)&/', trim($ret->href), $matches)) {
			$movieid = $matches['movieid'];
			self::$res['trailers']['url'] = self::AEBNSURL . self::TRAILERURL . $movieid;
		}

		return self::$res;
	}

	/**
	 * Gets the front and back cover of the box
	 *
	 * @return array
	 */
	protected function covers()
	{
		if ($ret = $this->_html->find('div#md-boxCover, img[itemprop=thumbnailUrl]', 1)) {
			$ret = trim($ret->src);
			if (strpos($ret, '//') === 0) {
				$ret = 'http:' . $ret;
			}
			self::$res['boxcover'] = str_ireplace('160w.jpg', 'xlf.jpg', $ret);
			self::$res['backcover'] = str_ireplace('160w.jpg', 'xlb.jpg', $ret);
		}
		return self::$res;
	}

	/**
	 * Gets the Genres "Categories".
	 *
	 * @return array
	 */
	protected function genres()
	{
		if ($ret = $this->_html->find('div.md-detailsCategories', 0)) {
			foreach ($ret->find('a[itemprop=genre]') as $genre) {
				self::$res['genres'][] = trim($genre->plaintext);
			}
		}
		self::$res['genres'] = array_unique(self::$res['genres']);
		return self::$res;
	}

	/**
	 * Gets the Cast Members "Stars" and Director if any
	 *
	 * @return array
	 */
	protected function cast()
	{
		if ($ret = $this->_html->find('div.starsFull', 0)) {
			foreach ($ret->find('span[itemprop=name]') as $star) {
				self::$res['cast'][] = trim($star->plaintext);
			}
		} else {
			if ($ret = $this->_html->find('div.detailsLink', 0)) {
				foreach ($ret->find('span') as $star) {
					if (strpos($star->plaintext, '/More/') !== false && strpos($star->plaintext, '/Stars/') !== false) {
						self::$res['cast'][] = trim($star->plaintext);
					}
				}
			}
		}

		return self::$res;
	}

	/**
	 * Gets the product information
	 *
	 * @return array
	 */
	protected function productInfo()
	{
		if ($ret = $this->_html->find('div#md-detailsLeft', 0)) {
			foreach ($ret->find('div') as $div) {
				foreach ($div->find('span') as $span) {
					$span->plaintext = rawurldecode($span->plaintext);
					$span->plaintext = preg_replace('/&nbsp;/', '', $span->plaintext);
					self::$res['productinfo'][] = trim($span->plaintext);
				}
			}
			if (false !== $key = array_search('Running Time:', self::$res['productinfo'])) {
				unset(self::$res['productinfo'][$key + 2]);
			}
			if (false !== $key = array_search("Director:", self::$res['productinfo'])) {
				self::$res['director'] = self::$res['productinfo'][$key + 1];
				unset(self::$res['productinfo'][$key], self::$res['productinfo'][$key + 1]);
			}
			self::$res['productinfo'] = array_chunk(self::$res['productinfo'], 2, false);
		}

		return self::$res;
	}

	/**
	 * Gets the synopsis "plot"
	 *
	 * @return array
	 *
	 */
	protected function synopsis()
	{
		if ($ret = $this->_html->find('span[itemprop=about]', 0)) {
			if ($ret === null) {
				if ($ret = $this->_html->find('div.movieDetailDescription', 0)) {
					self::$res['synopsis'] = preg_replace('/Description:\s/', '', self::$res['plot']);
				}
			} else {
				self::$res['synopsis'] = trim($ret->plaintext);
			}
		}

		return self::$res;
	}

	/**
	 * Searches for a XXX name
	 *
	 * @return bool
	 */
	public function processSite($movie)
	{
		if (empty($movie)) {
			return false;
		}
		$this->_response = false;
		$this->_trailerUrl = self::TRAILINGSEARCH . urlencode($movie);
		$this->_response = getUrl(self::IF18);
		if ($this->_response !== false) {
			$this->_html->load($this->_response);
			$count = count($this->_html->find('div.movie'));
			$i = 1;
			while ($count >= $i) {
				foreach ($this->_html->find('div.movie') as $mov) {
					$string = 'a#FTSMovieSearch_link_title_detail_' . $i;
					if ($ret = $mov->find($string, 0)) {
						$title = str_replace('/XXX/', '', $ret->title);
						$title = preg_replace('/\(.*?\)|[-._]/', ' ', $title);
						$title = trim($title);
						similar_text(strtolower($mov), strtolower($title), $p);
						if ($p >= 90) {
							$this->_title = trim($ret->title);
							$this->_trailerUrl = html_entity_decode($ret->href);
							$this->_directUrl = self::AEBNSURL . self::TRAILINGSEARCH . urlencode($movie);
							getUrl(self::AEBNSURL);
							return true;
						}
						continue;
					}
					$i++;
				}
			}

			return false;
		}

		return false;
	}

	/**
	 * Gets all the information
	 *
	 * @return array|bool
	 */
	protected function getAll()
	{
		$results = [];
		if (!empty($this->_directUrl)) {
			$results['title'] = $this->_title;
			$results['directurl'] = $this->_directUrl;
		}
		if (is_array($this->synopsis())) {
			$results = array_merge($results, $this->synopsis());
		}
		if (is_array($this->productInfo())) {
			$results = array_merge($results, $this->productInfo());
		}
		if (is_array($this->cast())) {
			$results = array_merge($results, $this->cast());
		}
		if (is_array($this->genres())) {
			$results = array_merge($results, $this->genres());
		}
		$covers = $this->covers();
		if (is_array($covers)) {
			$results = array_merge($results, $covers);
		}
		if (is_array($this->trailers())) {
			$results = array_merge($results, $this->trailers());
		}
		if (empty($results)) {
			return false;
		}

		return $results;
	}
}
