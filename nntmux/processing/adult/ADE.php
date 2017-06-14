<?php
namespace nntmux\processing\adult;

/**
 * Class adultdvdempire
 */
class ADE extends AdultMovies
{
	/**
	 * If a direct link is given parse it rather then search
	 * @var string
	 */
	public $directLink = '';

	/**
	 * If a string is found do call back.
	 * @var bool
	 */
	public $found = false;

	/**
	 * Search keyword
	 * @var string
	 */
	public $searchTerm = '';

	/**
	 * Define ADE Url here
	 */
	const ADE = 'http://www.adultdvdempire.com';

	/**
	 * Direct Url returned in getAll method
	 *
	 * @var string
	 */
	protected $_directUrl = "";

	/**
	 * If a url is found that matches the keyword
	 *
	 */
	protected $_urlFound;

	/**
	 * Sets the title in the getAll method
	 *
	 * @var string
	 */
	protected $_title = "";

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
	protected $_html;
	protected $_edithtml;
	protected $_ch;

	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->_html = new \simple_html_dom();
		$this->_edithtml = new \simple_html_dom();
	}

	/**
	 *
	 * Remove from memory if they were not removed
	 *
	 */
	public function __destruct()
	{
		$this->_html->clear();
		$this->_edithtml->clear();
		unset($this->_response, $this->_tmpResponse);
	}

	/**
	 * Gets Trailer Movies
	 * @return array - url, streamid, basestreamingurl
	 */
	public function trailers()
	{
		$this->_response = getRawHtml($this->_trailers . $this->_directUrl);
		$this->_html->load($this->_response);
		if (preg_match("/(\"|')(?P<swf>[^\"']+.swf)(\"|')/i", $this->_response, $matches)) {
			$this->_res['trailers']['url'] = self::ADE . trim(trim($matches['swf']), '"');
			if (preg_match('#(?:streamID:\s\")(?P<streamid>[0-9A-Z]+)(?:\")#',
				$this->_response,
				$matches)
			) {
				$this->_res['trailers']['streamid'] = trim($matches['streamid']);
			}
			if (preg_match('#(?:BaseStreamingUrl:\s\")(?P<baseurl>[\d]+.[\d]+.[\d]+.[\d]+)(?:\")#',
				$this->_response,
				$matches)
			) {
				$this->_res['trailers']['baseurl'] = $matches['baseurl'];
			}
		}
		unset($matches);
		$this->_html->clear();

		return $this->_res;
	}

	/**
	 * Gets cover images for the xxx release
	 * @return array - Boxcover and backcover
	 */
	public function covers()
	{
		if ($ret = $this->_html->find('div#Boxcover, img[itemprop=image]', 1)) {
			$this->_res['boxcover'] = preg_replace('/m\.jpg/', 'h.jpg', $ret->src);
			$this->_res['backcover'] = preg_replace('/m\.jpg/', 'bh.jpg', $ret->src);
		}

		return $this->_res;
	}

	/**
	 * Gets the synopsis
	 *
	 * @return array - plot
	 */
	public function synopsis()
	{
		if ($ret = $this->_html->find('meta[name="og:description"]', 0)->content()) {
			$this->_res['synopsis'] = trim($ret->innertext);
		}

		return $this->_res;
	}

	/**
	 * Gets the cast members and/or awards
	 *
	 * @param bool $awards - Include Awards? true/false
	 *
	 * @return array - cast, awards
	 */
	public function cast($awards = false)
	{
		$this->_tmpResponse = str_ireplace('Section Cast', 'scast', $this->_response);
		$this->_edithtml->load($this->_tmpResponse);


		if ($ret = $this->_edithtml->find('div[class=scast]', 0)) {
			$this->_tmpResponse = trim($ret->outertext);
			$ret = $this->_edithtml->load($this->_tmpResponse);
			foreach ($ret->find('a.PerformerName') as $a) {
				if ($a->plaintext !== '(bio)' && $a->plaintext !== '(interview)') {
					$this->_res['cast'][] = trim($a->plaintext);
					}
				}
			}
			if ($awards === true) {
				if ($ret->find('ul', 1)) {
					foreach ($ret->find('ul', 1)->find('li, strong') as $li) {
						$this->_res['awards'][] = trim($li->plaintext);
					}
				}
			}
			$this->_edithtml->clear();
			unset($ret, $this->_tmpResponse);

		return $this->_res;
	}

	/**
	 * Gets Genres, if exists return array else return false
	 * @return mixed array - Genres
	 */
	public function genres()
	{
		$genres = [];
		$this->_tmpResponse = str_ireplace('Section Categories', 'scat', $this->_response);
		$this->_edithtml->load($this->_tmpResponse);
		if ($ret = $this->_edithtml->find('div[class=scat]', 0)) {
			$ret = $ret->find('p', 0);
			$this->_tmpResponse = trim($ret->outertext);
			$ret = $this->_edithtml->load($this->_tmpResponse);

			foreach ($ret->find('a') as $categories) {
				$categories = trim($categories->plaintext);
				if (strpos($categories, ',') !== false) {
					$genres = explode(',', $categories);
					$genres = array_map('trim', $genres);
				} else {
					$genres[] = $categories;
				}
			}
			if (is_array($genres)) {
				$this->_res['genres'] = array_unique($genres);
			}
		}
		$this->_edithtml->clear();
		unset($this->_tmpResponse, $ret);
		return $this->_res;
	}

	/**
	 * Gets Product Information and/or Features
	 *
	 * @param bool $features Include features? true/false
	 *
	 * @return array - ProductInfo/Extras = features
	 */
	public function productInfo($features = false)
	{
		$dofeature = null;
		$this->_tmpResponse = str_ireplace('Section ProductInfo', 'spdinfo', $this->_response);
		$this->_edithtml->load($this->_tmpResponse);
		if ($ret = $this->_edithtml->find('div[class=spdinfo]', 0)) {
			$this->_tmpResponse = trim($ret->outertext);
			$ret                = $this->_edithtml->load($this->_tmpResponse);
			foreach ($ret->find("text") as $strong) {
				if (trim($strong->innertext) === 'Features') {
					$dofeature = true;
				}
				if ($dofeature !== true) {
					if (trim($strong->innertext) !== '&nbsp;') {
						$this->_res['productinfo'][] = trim($strong->innertext);
					}
				} else {
					if ($features === true) {
						$this->_res['extras'][] = trim($strong->innertext);
					}
				}
			}

			array_shift($this->_res['productinfo']);
			array_shift($this->_res['productinfo']);
			$this->_res['productinfo'] = array_chunk($this->_res['productinfo'], 2, false);
		}
		$this->_edithtml->clear();
		unset($this->_tmpResponse, $ret);

		return $this->_res;
	}

	/**
	 * Gets the direct link information and returns it
	 * @return array|bool
	 */
	public function getDirect()
	{
		if (!empty($this->directLink) && $this->getRawHtml() !== false) {
			$this->_html->load($this->_response);
			return $this->getAll();
		}
		return false;
	}

	/**
	 * Searches xxx name.
	 *
	 * @param string $movie
	 *
	 * @return bool - True if releases has 90% match, else false
	 */
	public function processSite($movie): bool
	{
		if (empty($movie)) {
			return false;
		}
		$this->_response = getRawHtml($this->_dvdQuery . rawurlencode($movie));
		if ($this->_response !== false) {
			$this->_html->load($this->_response);
			if ($ret = $this->_html->find('div[id=boxcover]', 0)) {
				$title = $ret->title;
				$title = str_replace('/XXX/', '', $title);
				$title = preg_replace('/\(.*?\)|[-._]/', ' ', $title);
				$ret   = (string)trim($ret->href);
				similar_text(strtolower($movie), strtolower($title), $p);
				if ($p >= 90) {
					$this->_urlFound  = $ret;
					$this->_directUrl = self::ADE . $ret;
					$this->_title     = trim($title);
					$this->_response = getRawHtml($this->_directUrl);
					$this->_html->load($this->_response);
					return true;
				}
				return false;
			}
			return false;
		}
		return false;
	}

	/**
	 * Gets All Information from the methods
	 *
	 * @return array
	 */
	protected function getAll()
	{
		$results = [];
		if (!empty($this->_directUrl)) {
			$results['directurl'] = $this->_directUrl;
			$results['title']     = $this->_title;
		}
		if (is_array($this->synopsis(true))) {
			$results = array_merge($results, $this->synopsis(true));
		}
		if (is_array($this->productInfo(true))) {
			$results = array_merge($results, $this->productInfo(true));
		}
		if (is_array($this->cast(true))) {
			$results = array_merge($results, $this->cast(true));
		}
		if (is_array($this->genres())) {
			$results = array_merge($results, $this->genres());
		}
		if (is_array($this->covers())) {
			$results = array_merge($results, $this->covers());
		}
		if (is_array($this->trailers())) {
			$results = array_merge($results, $this->trailers());
		}
		return $results;
	}
}
