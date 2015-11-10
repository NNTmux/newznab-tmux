<?php
namespace newznab;


use newznab\db\Settings;


/**
 * Class DnzbFailures
 */
class DnzbFailures
{
	/**
	 * @var \newznab\db\Settings
	 */
	public $pdo;

	/**
	 * @var ReleaseComments
	 */
	public $rc;

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
		$this->rc = new ReleaseComments(['Settings' => $this->pdo]);
	}

	/**
	 * @note Read failed downloads count for requested guid
	 *
	 * @param string $guid
	 */
	public function getFailedCount($guid)
	{
		$result = $this->pdo->query(sprintf('SELECT failed AS num FROM dnzb_failures WHERE guid = %s', $this->pdo->escapeString($guid)));
		return $result[0]['num'];
	}

	/**
	 * Get a count of failed releases for pager. used in admin manage failed releases list
	 */
	public function getCount()
	{
		$res = $this->pdo->queryOneRow("SELECT count(id) AS num FROM dnzb_failures");
		return $res["num"];
	}

	/**
	 * @note Get a range of releases. Used in admin manage list
	 *
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getFailedRange($start, $num)
	{
		if ($start === false) {
			$limit = '';
		} else {
			$limit = ' LIMIT ' . $start . ',' . $num;
		}

		return $this->pdo->query("SELECT r.*, concat(cp.title, ' > ', c.title) AS category_name
									FROM releases r
									RIGHT JOIN dnzb_failures df ON df.guid = r.guid
									LEFT OUTER JOIN category c ON c.id = r.categoryid
									LEFT OUTER JOIN category cp ON cp.id = c.parentid
									ORDER BY postdate DESC" . $limit
		);
	}

	/**
	 * @note Retrieve alternate release with same or similar searchname,
	 *       update failed count while doing it
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

		$this->updateFailed($guid);
		$rel = $this->pdo->queryOneRow(sprintf('SELECT id FROM releases WHERE guid = %s', $this->pdo->escapeString($guid)));
		$this->postComment($rel['id'], $rel['gid'], $userid);

		$alternate = $this->pdo->queryOneRow(sprintf('SELECT * FROM releases r
			WHERE r.searchname %s
			AND r.guid NOT IN (SELECT guid FROM dnzb_failures WHERE userid = %d)',
						$this->pdo->likeString($searchname),
						$userid
				)
		);
		return $alternate;
	}

	/**
	 * @note  Post comment for the release if that release has no comment for failure.
	 *        Only one user is allowed to post comment for that release, rest will just
	 *        update the failed count in dnzb_failures table
	 *
	 * @param $relid
	 * @param gid
	 * @param $uid
	 */
	public function postComment($relid, $gid, $uid)
	{
		$text = 'This release has failed to download properly. It might fail for other users too.
		This comment is automatically generated.';
		$dbl = $this->pdo->queryOneRow(sprintf('SELECT text FROM release_comments WHERE releaseid = %d', $relid));
		if ($dbl['text'] != $text){
			$this->rc->addComment($relid, $gid, $text, $uid, '');
		}
	}

	/**
	 * @note Update count of failed downloads for guid
	 *
	 * @param string $guid
	 */
	public function updateFailed($guid)
	{
		$this->pdo->queryExec(
				sprintf('UPDATE dnzb_failures SET failed = failed + 1 WHERE guid = %s', $this->pdo->escapeString($guid))
		);
	}
}
