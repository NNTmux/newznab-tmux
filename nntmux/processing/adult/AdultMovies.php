<?php

namespace nntmux\processing\adult;


use nntmux\XXX;

abstract class AdultMovies extends XXX
{
	// XXX Sources
	const SOURCE_NONE    = 0;
	const SOURCE_ADE    = 1;
	const SOURCE_ADM  = 2;
	const SOURCE_AEBN    = 3;
	const SOURCE_HOTMOVIES   = 4;
	const SOURCE_IAFD    = 5;
	const SOURCE_POPPORN    = 6;

	// Processing signifiers
	const PROCESS_ADE   =  0;
	const PROCESS_ADM = -1;
	const PROCESS_AEBN   = -2;
	const PROCESS_HOTMOVIES  = -3;
	const PROCESS_IAFD   = -4;
	const PROCESS_POPPORN   = -5;
	const NO_MATCH_FOUND = -6;
	const FAILED_PARSE   = -100;

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
	 * Updates the release tv_episodes_id status when scraper match is not found
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

	/**
	 * Retrieve releases for XXX processing
	 * Returns a PDO Object of rows or false if none found
	 *
	 * @param string $groupID -- ID of the usenet group to process
	 * @param string $guidChar -- threading method by first guid character
	 * @param int    $lookupSetting -- whether or not to use the API
	 * @param int    $status -- release processing status of tv_episodes_id
	 *
	 * @return false|int|\PDOStatement
	 */
	public function getXXXReleases($groupID = '', $guidChar = '', $lookupSetting = 1, $status = 0)
	{
		$ret = 0;
		if ($lookupSetting === 0) {
			return $ret;
		}

		$res = $this->pdo->queryDirect(
			sprintf('
				SELECT SQL_NO_CACHE r.searchname, r.id
				FROM releases r
				WHERE r.nzbstatus = 1
				AND r.xxxinfo_id = %d
				AND r.size > 1048576
				AND %s
				%s %s %s
				ORDER BY r.postdate+0 DESC
				LIMIT %d',
				$status,
				$this->catWhere,
				($groupID === '' ? '' : 'AND r.groups_id = ' . $groupID),
				($guidChar === '' ? '' : 'AND r.leftguid = ' . $this->pdo->escapeString($guidChar)),
				($lookupSetting === 2 ? 'AND r.isrenamed = 1' : ''),
				$this->movieqty
			)
		);
		return $res;
	}

}