<?php
/**
 * Copyright (c) 2011 Richard Hoppes.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * */
/**
 * Rotten Tomatoes API
 */
class RottenTomato
{
	/**
	 * API Urls
	 */
	const ROTTEN_TOMATOES_API_LISTS_DIRECTORY = 'http://api.rottentomatoes.com/api/public/v1.0/lists.json';
	const ROTTEN_TOMATOES_API_MOVIE_LISTS_DIRECTORY = 'http://api.rottentomatoes.com/api/public/v1.0/movies.json';
	const ROTTEN_TOMATOES_API_DVD_LISTS_DIRECTORY = 'http://api.rottentomatoes.com/api/public/v1.0/lists/dvds.json';
	const ROTTEN_TOMATOES_API_DVD_TOP_RENTALS = 'http://api.rottentomatoes.com/api/public/v1.0/lists/dvds/top_rentals.json';
	const ROTTEN_TOMATOES_API_MOVIE_SEARCH = 'http://api.rottentomatoes.com/api/public/v1.0/movies.json';
	const ROTTEN_TOMATOES_API_OPENING_MOVIES = 'http://api.rottentomatoes.com/api/public/v1.0/lists/movies/opening.json';
	const ROTTEN_TOMATOES_API_UPCOMING_MOVIES = 'http://api.rottentomatoes.com/api/public/v1.0/lists/movies/upcoming.json';
	const ROTTEN_TOMATOES_API_NEW_RELEASE_DVDS = 'http://api.rottentomatoes.com/api/public/v1.0/lists/dvds/new_releases.json';
	const ROTTEN_TOMATOES_API_IN_THEATERS = 'http://api.rottentomatoes.com/api/public/v1.0/lists/movies/in_theaters.json';
	const ROTTEN_TOMATOES_API_BOX_OFFICE = 'http://api.rottentomatoes.com/api/public/v1.0/lists/movies/box_office.json';
	const ROTTEN_TOMATOES_API_MOVIE_INFO = 'http://api.rottentomatoes.com/api/public/v1.0/movies/%s.json';
	const ROTTEN_TOMATOES_API_MOVIE_CAST = 'http://api.rottentomatoes.com/api/public/v1.0/movies/%s/cast.json';
	const ROTTEN_TOMATOES_API_MOVIE_REVIEWS = 'http://api.rottentomatoes.com/api/public/v1.0/movies/%s/reviews.json';
	/**
	 * Review types
	 */
	const REVIEW_TYPE_TOP_CRITIC = 'top_critic';
	const REVIEW_TYPE_ALL = 'all';
	const REVIEW_TYPE_DVD = 'dvd';
	/**
	 * API key
	 * @var string
	 */
	protected $apiKey;
	/**
	 * Timeout seconds
	 * @var int
	 */
	protected $timeoutSeconds;
	/**
	 * Country
	 * @var string
	 */
	protected $country;
	/**
	 * Initialize
	 * @param string $apiKey
	 * @param int $timeoutSeconds
	 * @param string $country
	 */
	public function __construct($apiKey, $timeoutSeconds = 15, $country = 'us') {
		$this->apiKey = $apiKey;
		$this->timeoutSeconds = $timeoutSeconds;
		$this->country = $country;
	}
	/**
	 * Movie search
	 * @throws Exception
	 * @param string $query search term
	 * @param int $page current page
	 * @return array results
	 */
	public function movieSearch($query, $page = 0) {
		$params = [];
		$params['q'] = $query;
		if ($page)
			$params['page'] = $page;
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_MOVIE_SEARCH, $params);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get movie reviews
	 * @throws Exception
	 * @param int $id rotten tomatoes id
	 * @param string $reviewType review type
	 * @param int $page current page
	 * @return array results
	 */
	public function getMovieReviews($id, $reviewType = self::REVIEW_TYPE_ALL, $page = 0) {
		$url = sprintf(self::ROTTEN_TOMATOES_API_MOVIE_REVIEWS, $id);
		$params = [];
		$params['review_type'] = $reviewType;
		if ($page)
			$params['page'] = $page;
		$result = $this->getResource($url, $params);
		if(!isset($result['reviews']) && !empty($result['reviews']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get movie cast
	 * @throws Exception
	 * @param int $id rotten tomatoes id
	 * @return
	 */
	public function getMovieCast($id) {
		$url = sprintf(self::ROTTEN_TOMATOES_API_MOVIE_CAST, $id);
		$result = $this->getResource($url);
		if(!isset($result['cast']) || empty($result['cast']))
			throw new Exception("No results");
		return $result['cast'];
	}
	/**
	 * Get movie info
	 * @throws Exception
	 * @param int $id rotten tomatoes id
	 * @return array
	 */
	public function getMovieInfo($id) {
		$url = sprintf(self::ROTTEN_TOMATOES_API_MOVIE_INFO, $id);
		$result = $this->getResource($url);
		if(!isset($result['id']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get new DVD releases
	 * @throws Exception
	 * @param int $page current page
	 * @return array results
	 */
	public function getNewDvdReleases($page = 0) {
		$params = [];
		if ($page)
			$params['page'] = $page;
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_NEW_RELEASE_DVDS, $params);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get movies that are coming soon
	 * @throws Exception
	 * @param int $page current page
	 * @return array results
	 */
	public function getUpcomingMovies($page = 0) {
		$params = [];
		if ($page)
			$params['page'] = $page;
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_UPCOMING_MOVIES, $params);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get movies currently in theaters
	 * @throws Exception
	 * @param int $page current page
	 * @return array results
	 */
	public function getMoviesInTheaters($page = 0) {
		$params = [];
		if ($page)
			$params['page'] = $page;
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_IN_THEATERS, $params);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get movies opening
	 * @throws Exception
	 * @return array results
	 */
	public function getOpeningMovies() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_OPENING_MOVIES);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get top box office results
	 * @throws Exception
	 * @return array results
	 */
	public function getMoviesBoxOffice() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_BOX_OFFICE);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get top DVD rentals
	 * @throws Exception
	 * @return array results
	 */
	public function getDvdTopRentals() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_DVD_TOP_RENTALS);
		if(!isset($result['movies']) && !empty($result['movies']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get Movie Urls
	 * @throws Exception
	 * @return array results
	 */
	public function getMovieListsDirectory() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_DVD_LISTS_DIRECTORY);
		if(!isset($result['links']) && !empty($result['links']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get list Urls
	 * @throws Exception
	 * @return array results
	 */
	public function getListsDirectory() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_LISTS_DIRECTORY);
		if(!isset($result['links']) && !empty($result['links']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get DVD API Urls
	 * @throws Exception
	 * @return array results
	 */
	public function getDvdListsDirectory() {
		$result = $this->getResource(self::ROTTEN_TOMATOES_API_DVD_LISTS_DIRECTORY);
		if(!isset($result['links']) && !empty($result['links']))
			throw new Exception("No results");
		return $result;
	}
	/**
	 * Get resource
	 * @param string $url
	 * @param array $params parameters (key/value pairs) for query string
	 * @return array decoded json response
	 */
	protected function getResource($url, $params = []) {
		$params['apikey'] = $this->apiKey;
		$params['country'] = $this->country;
		$queryString = http_build_query($params);
		$rawResponse = trim($this->httpRequest("{$url}?{$queryString}"));
		if (!$rawResponse)
			throw new Exception("No response");
		$decodedResponse = json_decode($rawResponse, true);
		if (!$decodedResponse)
			throw new Exception('Error parsing JSON response');
		if (isset($decodedResponse['error']))
			throw new Exception('API Error: ' + $decodedResponse['error']);
		return $decodedResponse;
	}
	/**
	 * Perform HTTP Request
	 * @param string $url url for request
	 * @return string|boolean result
	 */
	protected function httpRequest($url) {
		//Test for allow_url_fopen to be enabled.
		//Many hosts disable this for security
		if(function_exists('curl_init')){
			return $this->httpRequestCurl($url);
		}elseif(ini_get('allow_url_fopen')){
			return $this->httpRequestFopen($url);
		}else{
			return false;
		}
	}

	/**
	 * Perform HTTP Request with fopen
	 * @param string $url url for request
	 * @return string|boolean result
	 */
	protected function httpRequestFopen($url){
		$http = [];
		$http['method'] = 'GET';
		$http['timeout'] = $this->timeoutSeconds;
		$response = @fopen($url, 'r', false, stream_context_create(['http' => $http]));
		$result = false;
		if ($response)
			$result = $response;
		return $result;
	}

	/**
	 * Perform HTTP Request with curl
	 * @param string $url url for request
	 * @return string|boolean result
	 */
	protected function httpRequestCurl($url){
		// create curl resource
		$ch = curl_init();

		// set url
		curl_setopt($ch, CURLOPT_URL, $url);

		//return the transfer as a string
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//set timeout
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
		// $output contains the output string
		$response = curl_exec($ch);

		// close curl resource to free up system resources
		curl_close($ch);
		$result = false;
		if ($response)
			$result = $response;
		return $result;
	}
}