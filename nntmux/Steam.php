<?php
namespace nntmux;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use lithium\storage\session\adapter\Cookie;
use nntmux\db\DB;
use nntmux\utility\Utility;

class Steam
{

	/**
	 * @var
	 */
	public $cookie;

	/**
	 * @var
	 */
	public $searchTerm;

	const AGECHECKURL = 'http://store.steampowered.com/agecheck/app/';
	const CDNURL = 'http://cdn.akamai.steamstatic.com/steam/apps/';
	const GAMEURL = 'http://store.steampowered.com/app/';
	const STEAMURL = 'http://store.steampowered.com';
	const STEAMVARS = '/search/?category1=998&os=win&sort_order=ASC&page=1&term=';

	/**
	 * @var bool
	 */
	protected $_ageCheckSet = false;

	/**
	 * @var bool
	 */
	protected $_birthTime = false;

	/**
	 * @var
	 */
	protected $_ch;

	/**
	 * @var string
	 */
	protected $_directURL = '';

	/**
	 * @var
	 */
	protected $_editHtml;

	/**
	 * @var \simple_html_dom
	 */
	protected $_html;

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var CookieJar
	 */
	protected $cookiejar;

	/**
	 * @var DB
	 */
	protected $pdo;

	/**
	 * @var string
	 */
	protected $_indirectURL = '';

	/**
	 * @var bool
	 */
	protected $_lastAgeCheck = false;

	/**
	 * @var array
	 */
	protected $_postParams = [];

	/**
	 * @var array
	 */
	protected $_res = [];

	/**
	 * @var
	 */
	protected $_response;

	/**
	 * @var
	 */
	protected $_ret;

	/**
	 * @var
	 */
	protected $_steamGameID;

	/**
	 * @var string
	 */
	protected $_title = '';

	/**
	 * @var int
	 */
	protected $_totalResults = 0;

	public function __construct()
	{
		$this->_html = new \simple_html_dom();
		$this->_editHtml = new \simple_html_dom();
		$this->client = new Client();
		$this->cookiejar = new CookieJar();
		$this->pdo = new DB();
		if (!empty($this->cookie)) {
			$cookieJar = $this->cookiejar->setCookie(SetCookie::fromString($this->cookie));
			$this->client = new Client(['cookies' => $cookieJar]);
		}
	}

	/**
	 *
	 * Remove from memory
	 *
	 */
	public function __destruct()
	{
		$this->_html->clear();
		$this->_editHtml->clear();
		unset($this->_response);
	}

	/**
	 * Get Details of the game (Studio,Release Date, Developer)
	 *
	 * @return array
	 */
	public function details()
	{
		if ($this->_ret = $this->_html->find('div.details_block', 0)) {
			$textarr = [];
			foreach ($this->_ret->find('text') as $text) {
				$text = trim($text->plaintext);
				if (!empty($text)) {
					$textarr[] = rtrim($text, ':');
				}
			}
			$totaldetails = count($textarr) - 1;
			for ($i = 0; $i <= $totaldetails;) {
				if ($textarr[$i] === 'Release Date') {
					$pregmatchdate = $textarr[$i+1];
					if (preg_match_all('#(?P<day>[0-3]?\d)[\D]|(?P<month>Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)|(?P<year>(19|20)\d{2})#i',
						$pregmatchdate,
						$matches)) {

						$matches    = array_map('array_filter', $matches);
						$matches    = array_map('array_values', $matches);
						$matchday   = isset($matches['day'][0]) ? $matches['day'][0] : '1';
						$matchmonth = isset($matches['month'][0]) ? $matches['month'][0] : '';
						$matchyear  = isset($matches['year'][0]) ? $matches['year'][0] : '';
						if (!empty($matchday) && !empty($matchmonth) && !empty($matchyear)) {
							$textarr[$i + 1] = $matchmonth . '/' . $matchday . '/' . $matchyear;
						}
					}
				}
				$this->_res['gamedetails'][$textarr[$i]] = $textarr[$i+1];
				$i += 2;
			}
		}

		return $this->_res;
	}

	/**
	 * Game Description Snippet
	 *
	 * @return array
	 */
	public function gameDescription()
	{
		if ($this->_ret = $this->_html->find('div.game_description_snippet', 0)) {
			$this->_res['description'] = trim($this->_ret->plaintext);
		}

		return $this->_res;
	}

	/**
	 * Gets all Information for the game.
	 *
	 * @return array
	 */
	public function getAll()
	{
		$results = [];
		if (!empty($this->_directURL)) {
			$results['steamgameid'] = $this->_steamGameID;
			$results['directurl'] = $this->_directURL;
			$results['title'] = $this->_title;
		}
		if (is_array($this->gameDescription())) {
			$results = array_merge($results, $this->gameDescription());
		}
		if (is_array($this->details())) {
			$results = array_merge($results, $this->details());
		}
		if (is_array($this->rating())) {
			$results = array_merge($results, $this->rating());
		}
		if (is_array($this->images())) {
			$results = array_merge($results, $this->images());
		}
		if (is_array($this->trailer())) {
			$results = array_merge($results, $this->trailer());
		}

		return $results;
	}

	/**
	 * Gets the (cover and backdrop image)
	 *
	 * @return array
	 */
	public function images()
	{
		if ($this->_ret = $this->_html->find('img.game_header_image_full', 0)) {
			$this->_res['cover'] = $this->_ret->src;
		}
		if ($this->_ret = $this->_html->find('div.screenshot_holder', 0)) {
			if ($this->_ret = $this->_ret->find('a', 0)) {
				if(preg_match('/\?url\=(?<imgurl>.*)/', $this->_ret->href, $matches)){
					$this->_res['backdrop'] = trim($matches['imgurl']);
				}else{
					$this->_res['backdrop'] = trim($this->_ret->href);
				}

			}
		}

		return $this->_res;
	}

	/**
	 * Gets the metacritic Rating
	 *
	 * @return array
	 */
	public function rating()
	{
		if ($this->_ret = $this->_html->find('div#game_area_metascore', 0)) {
			$this->_res['rating'] = (int)$this->_ret->plaintext;
		}
		return $this->_res;
	}

	/**
	 * Searches for a game for a 90% match
	 *
	 * @return bool
	 */
	public function search()
	{
		if (empty($this->searchTerm)) {
			return false;
		}

		if ($this->getUrl(self::STEAMURL . self::STEAMVARS . rawurlencode($this->searchTerm)) !== false) {
			$title = null;
			if ($this->_ret = $this->_html->find('div.search_pagination_left', 0)) {
				if (preg_match('/\d+ of (?<total>\d+)/', trim($this->_ret->plaintext), $matches)) {
					$this->_totalResults = (int)$matches['total'];
				}
				if ($this->_totalResults > 0) {
					foreach ($this->_html->find('a.search_result_row') as $result) {
						foreach ($result->find('span.title') as $searchtitle) {
							$title = (string)trim($searchtitle->innertext);
						}
						if (!empty($title)) {
							similar_text(strtolower($title), strtolower($this->searchTerm), $p);
							if ($p > 90) {
								$this->_title = $title;
								if (isset($result->href)) {
									preg_match('/\/app\/(?<id>\d+)\//', $result->href, $matches);
									$this->_steamGameID = $matches['id'];
									$this->_directURL = self::GAMEURL . $this->_steamGameID . '/';
									$this->getUrl($result->href);
									$this->ageCheck();
									return true;
								} else {
									return false;
								}
							}
						}
					}
					if (empty($this->_title)) {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Gets the Video for the game
	 *
	 * @return array
	 */
	public function trailer()
	{
		if (preg_match('/store.steampowered.com\/video\//', $this->_response)) {
			$this->_res['trailer'] = self::STEAMURL . '/video/' . $this->_steamGameID;
			$this->getUrl($this->_res['trailer']);
			if (preg_match('@FILENAME\:\s+(?<videourl>\"\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w]+\)|([^[:punct:]\s]|/)))")@', $this->_response, $matches) && !empty($matches['videourl'])) {
				$this->_res['trailer'] = trim($matches['videourl'], '"');
			}
		} else {
			if (preg_match('/movie480.webm\?t\=(?<videoidentifier>\d+)/', $this->_response, $matches)
			) {
				$this->_res['trailer'] = self::CDNURL . $this->_steamGameID . '/movie480.webm?t=' . trim($matches['videoidentifier'], '"');
			}
		}

		return $this->_res;
	}

	/**
	 * Sets agecheck, retreive agecheck cookie information
	 *
	 */
	private function ageCheck()
	{
		if (!empty($this->cookie)) {
			$this->extractCookies(file_get_contents($this->cookie));
			if($this->_ageCheckSet === false) {
				$this->_postParams = [
					'snr' => '1_agecheck_agecheck__age-gate',
					'ageDay' => '1',
					'ageMonth' => 'May',
					'ageYear' => '1966'
				];

				$this->client->post(self::AGECHECKURL . $this->_steamGameID . '/', ['headers' => ['snr' => '1_agecheck_agecheck__age-gate',
																								  'ageDay' => '1',
																								  'ageMonth' => 'May',
																								  'ageYear' => '1966']]);
			}
		}
		$this->getUrl(self::GAMEURL . $this->_steamGameID . '/');
	}

	/**
	 * Gets Raw Html
	 *
	 * @param string $fetchURL
	 *
	 * @return bool
	 */
	private function getUrl($fetchURL)
	{
		if (!empty($fetchURL)) {
			try {
				$this->_response = $this->client->get($fetchURL)->getBody()->getContents();
			} catch (RequestException $e) {
				if ($e->hasResponse()) {
					if($e->getCode() === 404) {
						$this->pdo->log->doEcho($this->pdo->log->notice('Data not available on server'));
					} else if ($e->getCode() === 503) {
						$this->pdo->log->doEcho($this->pdo->log->notice('Service unavailable'));
					} else {
						$this->pdo->log->doEcho($this->pdo->log->notice('Unable to fetch data, http error reported: ' . $e->getCode()));
					}
				}
			}
		}

		if (!$this->_response) {
			return false;
		}
		$this->_html->load($this->_response);

		return true;
	}

	/**
	 * Extract values from a cookie and sets the values needed with the information.
	 *
	 * @param string $string The contents of the cookie file.
	 *
	 * @return bool True/False if lastagecheckage and birthtime exists.
	 */
	private function extractCookies($string)
	{
		$lines = explode("\n", $string);
		$this->_ageCheckSet = false;

		// iterate over lines
		foreach ($lines as $line) {

			// we only care for valid cookie def lines
			if (isset($line[0]) && substr_count($line, "\t") === 6) {

				// get tokens in an array
				$tokens = explode("\t", $line);

				// trim the tokens
				$tokens = array_map('trim', $tokens);

				// Extract the data
				if ($tokens[0] === 'store.steampowered.com') {
					if ($tokens[5] === 'birthtime') {
						$this->_birthTime = true;
					}
					if ($tokens[5] === 'lastagecheckage') {
						$this->_lastAgeCheck = true;
					}
				}
			}
		}

		if ($this->_birthTime === true && $this->_lastAgeCheck === true) {
			$this->_ageCheckSet = true;
		}
	return $this->_ageCheckSet;
}
