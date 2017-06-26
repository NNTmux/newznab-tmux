<?php

namespace nntmux\processing\adult;


abstract class AdultMovies
{
	/**
	 * @var \simple_html_dom
	 */
	protected $_html;

	/**
	 * @var string
	 */
	protected $_title;

	/**
	 * AdultMovies constructor.
	 *
	 * @param array $options
	 *
	 * @throws \Exception
	 */
	public function __construct(array $options = [])
	{
		$this->_html = new \simple_html_dom();
	}

	/**
	 * @param bool $extras
	 *
	 * @return mixed
	 */
	abstract protected function productInfo($extras = false);

	/**
	 * @return mixed
	 */
	abstract protected function covers();

	/**
	 * @return mixed
	 */
	abstract protected function synopsis();

	/**
	 * @return mixed
	 */
	abstract protected function cast();

	/**
	 * @return mixed
	 */
	abstract protected function genres();

	/**
	 * @param string $movie
	 *
	 * @return mixed
	 */
	abstract public function processSite($movie);

	/**
	 * Gets all information
	 *
	 * @return array|bool
	 */
	public function getAll()
	{
		$results = [];
		if (isset($this->_directUrl)) {
			$results['title'] = $this->_title;
			$results['directurl'] = $this->_directUrl;
		}
		if (is_array($this->synopsis())) {
			$results = array_merge($results, $this->synopsis());
		}
		if (is_array($this->productInfo(true))) {
			$results = array_merge($results, $this->productInfo(true));
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
		if (is_array($this->trailers())) {
			$results = array_merge($results, $this->trailers());
		}
		if (empty($results)) {
			return false;
		}

		return $results;
	}

	/**
	 * @return mixed
	 */
	abstract protected function trailers();


}