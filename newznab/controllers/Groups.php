<?php

use newznab\db\Settings;

/**
 * This class handles data access for groups.
 */
class Groups
{

	/**
	 * @var newznab\db\Settings
	 */
	public $pdo;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances.
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
	 * Get all group rows.
	 */
	public function getAll($orderby = null)
	{
		$order = ($orderby == null) ? 'name_desc' : $orderby;
		$orderArr = explode("_", $order);
		switch ($orderArr[0]) {
			case 'name':
				$orderfield = 'groups.name';
				break;
			case 'description':
				$orderfield = 'groups.description';
				break;
			case 'releases':
				$orderfield = 'num_releases';
				break;
			case 'updated':
				$orderfield = 'groups.last_updated';
				break;
			default:
				$orderfield = 'groups.name';
				break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';
		$orderby = $orderfield . " " . $ordersort;


		return $this->pdo->query(sprintf("SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
							FROM groups
							LEFT OUTER JOIN
							( SELECT groupid, COUNT(id) AS num FROM releases group by groupid ) rel ON rel.groupid = groups.id
							ORDER BY %s", $orderby
			)
		);
	}

	/**
	 * Get all group rows for use in a select list.
	 */
	public function getGroupsForSelect()
	{

		$categories = $this->pdo->query("SELECT * FROM groups WHERE active = 1 ORDER BY name");
		$temp_array = array();

		$temp_array[-1] = "--Please Select--";

		foreach ($categories as $category)
			$temp_array[$category["name"]] = $category["name"];

		return $temp_array;
	}

	/**
	 * Get a group row by its id.
	 */
	public function getByID($id)
	{


		return $this->pdo->queryOneRow(sprintf("select * from groups where id = %d ", $id));
	}

	/**
	 * Get all active group rows.
	 */
	public function getActive()
	{

		return $this->pdo->query("SELECT * FROM groups WHERE active = 1 ORDER BY name");
	}

	/**
	 * Get a group row by name.
	 */
	public function getByName($grp)
	{


		return $this->pdo->queryOneRow(sprintf("SELECT * FROM groups WHERE name = %s", $this->pdo->escapeString($grp)));
	}

	/**
	 * Get a group name using its id.
	 *
	 * @param int|string $id The group id.
	 *
	 * @return string Empty string on failure, groupName on success.
	 */
	public function getByNameByID($id)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT name FROM groups WHERE id = %d ", $id));
		return ($res === false ? '' : $res["name"]);
	}

	/**
	 * Get a group name using its name.
	 *
	 * @param string $name The group name.
	 *
	 * @return string Empty string on failure, groupid on success.
	 */
	public function getIDByName($name)
	{

		$res = $this->pdo->queryOneRow(sprintf("SELECT id FROM groups WHERE name = %s", $this->pdo->escapeString($name)));
		return ($res === false ? '' : $res["id"]);
	}

	/**
	 * Get count of all groups, filter by name.
	 */
	public function getCount($groupname = "", $activeonly = false)
	{


		$grpsql = '';
		if ($groupname != "")
			$grpsql .= sprintf("and groups.name like %s ", $this->pdo->escapeString("%" . $groupname . "%"));

		if ($activeonly == true)
			$grpsql .= "and active=1 ";

		$res = $this->pdo->queryOneRow(sprintf("select count(id) as num from groups where 1=1 %s", $grpsql));

		return $res["num"];
	}

	/**
	 * Get groups rows for browse list by limit.
	 */
	public function getRange($start, $num, $groupname = "", $activeonly = false)
	{

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$grpsql = '';
		if ($groupname != "")
			$grpsql .= sprintf("and groups.name like %s ", $this->pdo->escapeString("%" . $groupname . "%"));
		if ($activeonly == true)
			$grpsql .= "and active=1 ";

		$sql = sprintf("SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
							FROM groups
							LEFT OUTER JOIN
							(
							SELECT groupid, COUNT(id) AS num FROM releases group by groupid
							) rel ON rel.groupid = groups.id WHERE 1=1 %s ORDER BY groups.name " . $limit, $grpsql
		);

		return $this->pdo->query($sql);
	}

	/**
	 * Update an existing group.
	 *
	 * @param Array $group
	 *
	 * @return bool
	 */
	public function update($group)
	{

		$minFileString =
			($group["minfilestoformrelease"] == '' ?
				"minfilestoformrelease = NULL," :
				sprintf(" minfilestoformrelease = %d,", $this->formatNumberString($group["minfilestoformrelease"], false))
			);

		$minSizeString =
			($group["minsizetoformrelease"] == '' ?
				"minsizetoformrelease = NULL" :
				sprintf(" minsizetoformrelease = %d", $this->formatNumberString($group["minsizetoformrelease"], false))
			);

		return $this->pdo->queryExec(
			sprintf(
				"UPDATE groups
				SET name = %s, description = %s, backfill_target = %s, first_record = %s, last_record = %s,
				last_updated = NOW(), active = %s, backfill = %s, %s %s, regexmatchonly = %s
				WHERE id = %d",
				$this->pdo->escapeString(trim($group["name"])),
				$this->pdo->escapeString(trim($group["description"])),
				$this->formatNumberString($group["backfill_target"]),
				$this->formatNumberString($group["first_record"]),
				$this->formatNumberString($group["last_record"]),
				$this->formatNumberString($group["active"]),
				$this->formatNumberString($group["backfill"]),
				$minFileString,
				$minSizeString,
				$group["regexmatchonly"],
				$group["id"]
			)
		);
	}

	/**
	 * Add a new group.
	 *
	 * @param array $group
	 *
	 * @return bool
	 */
	public function add($group)
	{
		$minFileString =
			($group["minfilestoformrelease"] == '' ?
				"NULL" :
				sprintf("%d", $this->formatNumberString($group["minfilestoformrelease"], false))
			);

		$minSizeString =
			($group["minsizetoformrelease"] == '' ?
				"NULL" :
				sprintf("%d", $this->formatNumberString($group["minsizetoformrelease"], false))
			);

		$regexmatchonly =
			($group["regexmatchonly"] == '' ?
				'0' :
				sprintf("%d", $this->formatNumberString($group["regexmatchonly"], false))
			);


		return $this->pdo->queryInsert(
			sprintf("
				INSERT INTO groups
					(name, description, backfill_target, first_record, last_record, last_updated,
					active, backfill, minfilestoformrelease, minsizetoformrelease, regexmatchonly)
				VALUES (%s, %s, %s, %s, %s, NOW(), %s, %s, %s, %s, %s)",
				$this->pdo->escapeString(trim($group["name"])),
				$this->pdo->escapeString(trim($group["description"])),
				$this->formatNumberString($group["backfill_target"]),
				$this->formatNumberString($group["first_record"]),
				$this->formatNumberString($group["last_record"]),
				$this->formatNumberString($group["active"]),
				$this->formatNumberString($group["backfill"]),
				$minFileString,
				$minSizeString,
				$regexmatchonly
			)
		);
	}

	/**
	 * Format numeric string when adding/updating groups.
	 *
	 * @param string $setting
	 * @param bool   $escape
	 *
	 * @return string|int
	 */
	protected function formatNumberString($setting, $escape=true)
	{
		$setting = trim($setting);
		if ($setting === "0" || !is_numeric($setting)) {
			$setting = '0';
		}

		return ($escape ? $this->pdo->escapeString($setting) : (int)$setting);
	}

	/**
	 * Delete a group.
	 */
	public function delete($id)
	{

		return $this->pdo->queryExec(sprintf("DELETE from groups where id = %d", $id));
	}

	/**
	 * Reset a group.
	 *
	 * @param string|int $id The group id.
	 *
	 * @return bool
	 */
	public function reset($id)
	{
		// Remove rows from collections / binaries / parts.
		(new \Binaries(['Groups' => $this, 'Settings' => $this->pdo]))->purgeGroup($id);

		// Remove rows from part repair.
		$this->pdo->queryExec(sprintf("DELETE FROM partrepair WHERE groupid = %d", $id));

		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS binaries_%d', $id));
		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS parts_%d', $id));
		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS partrepair_%d', $id));

		// Reset the group stats.
		return $this->pdo->queryExec(
			sprintf("
				UPDATE groups
				SET backfill_target = 0, first_record = 0, first_record_postdate = NULL, last_record = 0,
					last_record_postdate = NULL, last_updated = NULL
				WHERE id = %d", $id)
		);
	}

	/**
	 * Reset all groups.
	 *
	 * @return bool
	 */
	public function resetall()
	{
		$this->pdo->queryExec("TRUNCATE TABLE binaries");
		$this->pdo->queryExec("TRUNCATE TABLE parts");
		$this->pdo->queryExec("TRUNCATE TABLE partrepair");
		$groups = $this->pdo->query("SELECT id FROM groups");
		foreach ($groups as $group) {
			$this->pdo->queryExec('DROP TABLE IF EXISTS binaries_' . $group['id']);
			$this->pdo->queryExec('DROP TABLE IF EXISTS parts_' . $group['id']);
			$this->pdo->queryExec('DROP TABLE IF EXISTS partrepair_' . $group['id']);
		}

		// Reset the group stats.
		return $this->pdo->queryExec("
			UPDATE groups
			SET backfill_target = 0, first_record = 0, first_record_postdate = NULL, last_record = 0,
				last_record_postdate = NULL, last_updated = NULL, active = 0"
		);
	}


	/**
	 * Update the list of newsgroups from nntp provider matching a regex and return an array of messages.
	 */
	function addBulk($groupList, $active = 1, $backfill = 1)
	{
		$ret = array();

		if ($groupList == "") {
			$ret[] = "No group list provided.";
		} else {
			$nntp = new Nntp(['Echo' => false]);
			if (!$nntp->doConnect()) {
				$ret[] = "Failed to get NNTP connection";

				return $ret;
			}
			$groups = $nntp->getGroups();
			$nntp->doQuit();

			$regfilter = "/(" . str_replace(array('.', '*'), array('\.', '.*?'), $groupList) . ")$/";

			foreach ($groups AS $group) {
				if (preg_match($regfilter, $group['group']) > 0) {
					$res = $this->pdo->queryOneRow(sprintf("SELECT id FROM groups WHERE name = %s ", $this->pdo->escapeString($group['group'])));
					if ($res) {

						$this->pdo->queryExec(sprintf("update groups SET active = %d where id = %d", $active, $res["id"]));
						$ret[] = array('group' => $group['group'], 'msg' => 'Updated');
					} else {
						$desc = "";
						$this->pdo->queryInsert(sprintf("INSERT INTO groups (name, description, active, backfill) VALUES (%s, %s, %d, %s)", $this->pdo->escapeString($group['group']), $this->pdo->escapeString($desc), $active, $backfill));
						$ret[] = array('group' => $group['group'], 'msg' => 'Created');
					}
				}
			}
		}

		return $ret;
	}

	/**
	 * @param     $id
	 * @param int $status
	 *
	 * @return string
	 */
	public function updateGroupStatus($id, $status = 0)
	{
		$this->pdo->queryExec(sprintf("UPDATE groups SET active = %d WHERE id = %d", $status, $id));
		return "Group $id has been " . (($status == 0) ? 'deactivated' : 'activated') . '.';
	}

	/**
	 * @param     $id
	 * @param int $status
	 *
	 * @return string
	 */
	public function updateBackfillStatus($id, $status = 0)
	{
		$this->pdo->queryExec(sprintf("UPDATE groups SET backfill = %d WHERE id = %d", $status, $id));
		return "Group $id has been " . (($status == 0) ? 'deactivated' : 'activated') . '.';
	}

	/**
	 * @return array
	 */
	public function getActiveBackfill()
	{
		return $this->pdo->query("SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY name");
	}

	/**
	 * @return array
	 */
	public function getActiveByDateBackfill()
	{
		return $this->pdo->query("SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY first_record_postdate DESC");
	}

	/**
	 * @var array
	 */
	private $cbppTableNames;

	/**
	 * Get the names of the binaries/parts/part repair tables.
	 * If TPG is on, try to create new tables for the groupid, if we fail, log the error and exit.
	 *
	 * @param bool $tpgSetting false, tpg is off in site setting, true tpg is on in site setting.
	 * @param int  $groupID    id of the group.
	 *
	 * @return array The table names.
	 */
	public function getCBPTableNames($tpgSetting, $groupID)
	{
		$groupKey = ($groupID . '_' . (int) $tpgSetting);

		// Check if buffered and return. Prevents re-querying MySQL when TPG is on.
		if (isset($this->cbppTableNames[$groupKey])) {
			return $this->cbppTableNames[$groupKey];
		}

		$tables = [];
		$tables['bname']  = 'binaries';
		$tables['pname']  = 'parts';
		$tables['prname'] = 'partrepair';

		if ($tpgSetting === true) {
			if ($groupID == '') {
				exit('Error: You must use .../misc/update_scripts/nix_scripts/multiprocessing/releases.php since you have enabled TPG!');
			}

			if ($this->createNewTPGTables($groupID) === false && NN_ECHOCLI) {
				exit('There is a problem creating new TPG tables for this group id: ' . $groupID . PHP_EOL);
			}

			$groupEnding = '_' . $groupID;
			$tables['bname']  .= $groupEnding;
			$tables['pname']  .= $groupEnding;
			$tables['prname'] .= $groupEnding;
		}

		// Buffer.
		$this->cbppTableNames[$groupKey] = $tables;

		return $tables;
	}

	/**
	 * @return array
	 */
	public function getActiveIDs()
	{
		return $this->pdo->query("SELECT id FROM groups WHERE active = 1 ORDER BY name");
	}

	/**
	 * Set the backfill to 0 when the group is backfilled to max.
	 *
	 * @param $name
	 */
	public function disableForPost($name)
	{
		$this->pdo->queryExec(sprintf("UPDATE groups SET backfill = 0 WHERE name = %s", $this->pdo->escapeString($name)));
	}

	/**
	 * Check if the tables exists for the group_id, make new tables for table per group.
	 *
	 * @param int $groupID
	 *
	 * @return bool
	 */
	public function createNewTPGTables($groupID)
	{
		foreach (['binaries', 'parts', 'partrepair'] as $tableName) {
			if ($this->pdo->queryExec(sprintf('SELECT * FROM %s_%s LIMIT 1', $tableName, $groupID), true) === false) {
				if ($this->pdo->queryExec(sprintf('CREATE TABLE %s_%s LIKE %s', $tableName, $groupID, $tableName), true) === false) {
					return false;
				} /*else {
					if ($tableName === 'binaries') {
						$this->pdo->queryExec(
							sprintf(
								'CREATE TRIGGER delete_binaries_%s BEFORE DELETE ON binaries_%s FOR EACH ROW BEGIN' .
								' DELETE FROM parts_%s WHERE binaryid = OLD.id; END',
								$groupID, $groupID, $groupID
							)
						);
					}
				}*/
			}
		}
		return true;
	}

	/**
	 * Purge a single group or all groups.
	 *
	 * @param int|string|bool $id The group id. If false, purge all groups.
	 */
	public function purge($id = false)
	{
		if ($id === false) {
			$this->resetall();
		} else {
			$this->reset($id);
		}

		$releaseArray = $this->pdo->queryDirect(
			sprintf("SELECT id, guid FROM releases %s", ($id === false ? '' : 'WHERE groupid = ' . $id))
		);

		if ($releaseArray instanceof \Traversable) {
			$releases = new \Releases(['Settings' => $this->pdo, 'Groups' => $this]);
			$nzb = new \NZB($this->pdo);
			$releaseImage = new \ReleaseImage($this->pdo);
			foreach ($releaseArray as $release) {
				$releases->deleteSingle(['g' => $release['guid'], 'i' => $release['id']], $nzb, $releaseImage);
			}
		}
	}

	/**
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getCountActive($groupname="")
	{
		$res = $this->pdo->queryOneRow(
			sprintf("
				SELECT COUNT(id) AS num
				FROM groups
				WHERE 1 = 1 %s
				AND active = 1",
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%".$groupname."%")
					)
					: ''
				)
			)
		);
		return $res["num"];
	}

	/**
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getCountInactive($groupname="")
	{
		$res = $this->pdo->queryOneRow(
			sprintf("
				SELECT COUNT(id) AS num
				FROM groups
				WHERE 1 = 1 %s
				AND active = 0",
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%".$groupname."%")
					)
					: ''
				)
			)
		);
		return $res["num"];
	}

	/**
	 * @param        $start
	 * @param        $num
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getRangeActive($start, $num, $groupname="")
	{
		return $this->pdo->query(
			sprintf("
				SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
				FROM groups
				LEFT OUTER JOIN
					(SELECT groupid, COUNT(id) AS num
						FROM releases
						GROUP BY groupid
					) rel
				ON rel.groupid = groups.id
				WHERE 1 = 1 %s
				AND active = 1
				ORDER BY groups.name " . ($start === false ? '' : " LIMIT " . $num . " OFFSET " .$start),
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%".$groupname."%")
					)
					: ''
				)
			)
		);
	}

	/**
	 * @param        $start
	 * @param        $num
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getRangeInactive($start, $num, $groupname="")
	{
		return $this->pdo->query(
			sprintf("
				SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
				FROM groups
				LEFT OUTER JOIN
					(SELECT groupid, COUNT(id) AS num
						FROM releases
						GROUP BY groupid
					) rel
				ON rel.groupid = groups.id
				WHERE 1 = 1 %s
				AND active = 0
				ORDER BY groups.name " . ($start === false ? '' : " LIMIT ".$num." OFFSET ".$start),
				($groupname !== ''
					? sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%".$groupname."%")
					)
					: ''
				)
			)
		);
	}
}