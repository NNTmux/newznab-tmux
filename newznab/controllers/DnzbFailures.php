<?php


use newznab\db\Settings;


/**
 * Class DnzbFailures
 */
class DnzbFailures
{
	/**
	 * @var array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
	}

	/**
	 * @param string $guid
	 *
	 * @return int|bool
	 */
	public function getFailedCount($guid)
	{
		$failed = $this->pdo->query(sprintf('SELECT COUNT(userid) AS num FROM dnzb_failures WHERE guid = %s', $this->pdo->escapeString($guid)));
		foreach($failed as $fail ){
			return $fail['num'];
		}
		return false;

	}

	/**
	 * Retrieve alternate release with same or similar searchname
	 *
	 * @param string $guid
	 * @param string $searchname
	 * @param string $userid
	 * @return string
	 */
	public function getAlternate($guid, $searchname, $userid)
	{
		$this->pdo->queryInsert(sprintf("INSERT IGNORE INTO dnzb_failures (userid, guid) VALUES (%d, %s)",
				$userid,
				$this->pdo->escapeString($guid)
			)
		);

		$alternate = $this->pdo->queryOneRow(sprintf('SELECT * FROM releases r
			WHERE r.searchname %s
			AND r.guid NOT IN (SELECT guid FROM dnzb_failures WHERE userid = %d)',
				$this->pdo->likeString($searchname),
				$userid
			)
		);
		return $alternate;
	}
}