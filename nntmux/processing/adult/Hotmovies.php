<?php
namespace nntmux;

use nntmux\processing\adult\AdultMovies;

class Hotmovies extends AdultMovies
{

	/**
	 * Constant Urls used within this class
	 * Needed Search Queries Variables
	 */
	const EXTRASEARCH = '&complete=on&search_in=video_title';
	const HMURL = 'http://www.hotmovies.com';
	const IF18 = true;
	const TRAILINGSEARCH = '/search.php?words=';
	/**
	 * Keyword Search.
	 *
	 * @var string
	 */
	public $searchTerm = '';
	/**
	 * Define a cookie location
	 *
	 * @var string
	 */
	public $cookie = '';
	/**
	 * If a direct link is set parse it instead of search for it.
	 *
	 * @var string
	 */
	public $directLink = '';
	/**
	 * Sets the direct url in the getAll method
	 *
	 * @var string
	 */
	protected $_directUrl = '';

	/**
	 * Sets the link to get in curl
	 *
	 * @var string
	 */
	protected $_getLink = '';

	/**
	 * Simple Html Dom Object
	 *
	 * @var \simple_html_dom
	 */
	protected $_html;

	/**
	 * POST parameters used with curl
	 *
	 * @var array
	 */
	protected $_postParams = [];

	/**
	 * Results return from some methods
	 *
	 * @var array
	 */
	protected $_res = [];

	/**
	 * Raw Html from Curl
	 *
	 */
	protected $_response;

	/**
	 * Sets the title in the getAll method
	 *
	 * @var string
	 */
	protected $_title = '';

	/**
	 * Hotmovies constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->_html = new \simple_html_dom();

		// Set a cookie to override +18 warning.
		if (!empty($this->cookie)) {
			@$this->getUrl();
		}
	}

	/*
	 * Remove from memory if it still exists
	 */

	/**
	 * Get Raw html of webpage
	 *
	 * @param bool $usepost
	 *
	 * @return bool
	 */
	private function getUrl($usepost = false)
	{
		if (!empty($this->_getLink)) {
			$ch = curl_init($this->_getLink);
		} else {
			$ch = curl_init(self::HMURL);
		}
		if (!empty($this->directLink)) {
			$ch = curl_init($this->directLink);
			$this->directLink = '';
		}
		if ($usepost === true){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_postParams);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1944.0 Safari/537.36');
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		if (!empty($this->cookie)) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		}
		$this->_response = curl_exec($ch);
		if (!$this->_response) {
			curl_close($ch);

			return false;
		}
		$this->_html->load($this->_response);
		curl_close($ch);
		return true;
	}

	public function __destruct()
	{
		$this->_html->clear();
		unset($this->_response, $this->_res);
	}

	/**
	 * Directly gets the link if directlink is set, and parses it.
	 *
	 * @return bool|array
	 */
	public function getDirect()
	{
		if (!empty($this->directLink)) {
			if ($this->getUrl() === false) {
				return false;
			} else {
				return $this->getAll();
			}
		}
		return false;
	}

	/**
	 * Gets all information
	 * @return bool|array
	 */
	public function getAll()
	{
		$results = [];
		if (!empty($this->_directUrl)) {
			$results['title'] = $this->_title;
			$results['directurl'] = $this->_directUrl;
		}
		if (is_array($this->sypnosis())) {
			$results = array_merge($results, $this->sypnosis());
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
		if (is_array($this->covers())) {
			$results = array_merge($results, $this->covers());
		}

		if (empty($results) === true){
			return false;
		}else{
			return $results;
		}
	}

	/**
	 * Gets the sypnosis
	 *
	 * @return array
	 */
	public function sypnosis()
	{
		if ($this->_html->find('.desc_link', 0)) {
			preg_match('/var descfullcontent = (?<content>.*)/', $this->_response,$matches);
			if (is_array($matches)) {
				$this->_res['sypnosis'] = rawurldecode($matches['content']);
			}
		}

		return $this->_res;
	}

	/**Process ProductInfo
	 *
	 * @return array
	 */
	public function productInfo()
	{
		$studio = false;
		$director = false;
		if ($ret = $this->_html->find('div.page_video_info', 0)) {
			foreach ($ret->find('text') as $e) {
				$e = trim($e->innertext);
				$rArray = [
					',',
					'...',
					'&nbsp:'
				];
				$e = str_replace($rArray, '', $e);
				if (stripos($e, 'Studio:') !== false) {
					$studio = true;
				}
				if (strpos($e, 'Director:') !== false) {
					$director = true;
					$e = null;
				}
				if ($studio === true) {
					if (stripos($e, 'Custodian of Records') === false) {
						if (stripos($e, 'Description') === false) {

							if ($director === true && !empty($e)) {
								$this->_res['director'] = $e;
								$e = null;
								$director = false;
							}
							if (!empty($e)) {
								$this->_res['productinfo'][] = $e;
							}
						} else {
							break;
						}
					} else {
						break;
					}
				}
			}
		}
		if (is_array($this->_res['productinfo'])) {
			$this->_res['productinfo'] = array_chunk($this->_res['productinfo'], 2, false);
		}

		return $this->_res;
	}

	/**
	 * Gets the cast members and director
	 *
	 *@return array
	 */
	public function cast()
	{
		$cast = null;
		if ($this->_html->find('a[itemprop=actor]')) {
			foreach ($this->_html->find('a[itemprop=actor]') as $e) {
				$e = trim($e->title);
				$e = preg_replace('/\((.*)\)/','',$e);
				$cast[] = trim($e);
			}
			$this->_res['cast'] = $cast;

		}

		return $this->_res;
	}

	/**
	 * Gets categories
	 *
	 *@return array
	 */
	public function genres()
	{
		$genres = [];
		if ($ret = $this->_html->find('div.categories',0)) {
			foreach ($ret->find('a') as $e) {
				if (strpos($e->title, '->') !== false) {
					$e = explode('->',$e->plaintext);
					$genres[] = trim($e[1]);
				}
			}
			$this->_res['genres'] = $genres;

		}

		return $this->_res;
	}

	/**
	 * Get Box Cover Images
	 * @return bool|array - boxcover,backcover
	 */
	public function covers()
	{
		if ($ret = $this->_html->find('div#large_cover, img#cover', 1)) {
			$this->_res['boxcover'] = trim($ret->src);
			$this->_res['backcover'] = str_ireplace('.cover', '.back', trim($ret->src));
		}else{
			return false;
		}

		return $this->_res;
	}

	/**
	 * Searches for match against searchterm
	 * @return bool, true if search >= 90%
	 */
	public function search()
	{
		if (empty($this->searchTerm)) {
			return false;
		}
		$this->_getLink = self::HMURL . self::TRAILINGSEARCH . urlencode($this->searchTerm) . self::EXTRASEARCH;
		if ($this->getUrl() === false) {
			return false;
		} else {
			if ($ret = $this->_html->find('h3[class=title]', 0)) {
				if ($ret->find('a[title]',0)){
					$ret = $ret->find('a[title]', 0);
					$title = trim($ret->title);
					$title = str_replace('/XXX/', '', $title);
					$title = preg_replace('/\(.*?\)|[-._]/', ' ', $title);
					$this->_getLink = trim($ret->href);
					$this->_directUrl = trim($ret->href);
				}
			} else {
				return false;
			}
			if (!empty($title)) {
				similar_text($this->searchTerm, $title, $p);
				if ($p >= 90) {
					$this->_title = $title;
					// 90$ match found, load the url to start parsing
					$this->getUrl();
					unset($ret);

					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
	}
}
