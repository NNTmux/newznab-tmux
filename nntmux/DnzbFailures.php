<?php
namespace nntmux;


use App\Models\DnzbFailure;
use nntmux\db\DB;


/**
 * Class DnzbFailures
 */
class DnzbFailures
{
	const FAILED = 1;
	/**
	 * @var DB
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

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
		$this->rc = new ReleaseComments(['Settings' => $this->pdo]);
	}

	/**
	 * Read failed downloads count for requested release_id
	 *
	 *
	 * @param $relId
	 *
	 * @return bool|mixed
	 */
	public function getFailedCount($relId)
	{
		$result = DnzbFailure::query()->where('release_id', $relId)->value('failed');
		if (!empty($result)) {
			return $result;
		}
		return false;
	}

	/**
	 * @return int
	 */
	public function getCount(): int
	{
		return DnzbFailure::query()->count('release_id');
	}

	/**
	 * Get a range of releases. used in admin manage list
	 *
	 * @param $start
	 * @param $num
	 *
	 * @return array
	 */
	public function getFailedRange($start, $num): array
	{
		if ($start === false) {
			$limit = '';
		} else {
			$limit = ' LIMIT ' . $start . ',' . $num;
		}

		return $this->pdo->query("
			SELECT r.*, CONCAT(cp.title, ' > ', c.title) AS category_name
			FROM releases r
			RIGHT JOIN dnzb_failures df ON df.release_id = r.id
			LEFT OUTER JOIN categories c ON c.id = r.categories_id
			LEFT OUTER JOIN categories cp ON cp.id = c.parentid
			ORDER BY postdate DESC" . $limit
		);
	}

	/**
	 * Retrieve alternate release with same or similar searchname
	 *
	 * @param string $guid
	 * @param string $userid
	 *
	 * @return string|array
	 * @throws \Exception
	 */
	public function getAlternate($guid, $userid)
	{
		$rel = $this->pdo->queryOneRow(
			sprintf('
				SELECT id, searchname, categories_id
				FROM releases
				WHERE guid = %s',
				$this->pdo->escapeString($guid)
			)
		);

		if ($rel === false) {
			return false;
		}

		$this->pdo->queryInsert(
			sprintf('
				INSERT IGNORE INTO dnzb_failures (release_id, users_id, failed)
				VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE failed = failed + 1',
				$rel['id'],
				$userid,
				self::FAILED

			)
		);

		// If we didn't actually insert the row, don't add a comment
		//Commenting out the code as return value is always 0
		/*
		if (is_numeric($insert) && $insert > 0) {
			$this->postComment($rel['id'], $rel['gid'], $userid);
		}
		*/

		$alternate = $this->pdo->queryOneRow(
			sprintf('
				SELECT r.guid
				FROM releases r
				LEFT JOIN dnzb_failures df ON r.id = df.release_id
				WHERE r.searchname %s
				AND df.release_id IS NULL
				AND r.categories_id = %d
				AND r.id != %d
				ORDER BY r.postdate DESC',
				$this->pdo->likeString($rel['searchname'], true, true),
				$rel['categories_id'],
				$rel['id']
			)
		);

		return $alternate;
	}

	/**
	 * Post comment for the release if that release has no comment for failure.
	 * Only one user is allowed to post comment for that release, rest will just
	 * update the failed count in dnzb_failures table
	 *
	 * @param $relid
	 * @param $gid
	 * @param $uid
	 *
	 * @throws \Exception
	 */
	public function postComment($relid, $gid, $uid): void
	{
		$dupe = false;
		$text = 'This release has failed to download properly. It might fail for other users too.
		This comment is automatically generated.';

		$check = $this->pdo->queryDirect(
				sprintf('
				SELECT text
				FROM release_comments
				WHERE releases_id = %d',
				$relid
				)
		);

		if ($check instanceof \Traversable) {
			foreach ($check AS $dbl) {
				if ($dbl['text'] === $text) {
					$dupe = true;
					break;
				}
			}
		}
		if ($dupe === false) {
			$this->rc->addComment($relid, $gid, $text, $uid, '');
		}
	}
}
