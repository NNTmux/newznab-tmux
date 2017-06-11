<?php

namespace nntmux\processing\adult;

use nntmux\XXX;

abstract class AdultMovies extends XXX
{
	/**
	 * AdultMovies constructor.
	 *
	 * @param array $options
	 *
	 * @throws \Exception
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
	}

	/**
	 * @return mixed
	 */
	abstract protected function productInfo();

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
	abstract protected function processSite($movie);

	/**
	 * @return mixed
	 */
	abstract protected function getAll();

	/**
	 * @return mixed
	 */
	abstract protected function trailers();

	/**
	 * Updates the release xxxinfo_id status when scraper match is not found
	 *
	 * @param $status
	 * @param $Id
	 */
	public function setXXXNotFound($status, $Id): void
	{
		$this->pdo->queryExec(
			sprintf('
				UPDATE releases
				SET xxxinfo_id = %d
				WHERE %s
				AND id = %d',
				$status,
				$this->catWhere,
				$Id
			)
		);
	}
}