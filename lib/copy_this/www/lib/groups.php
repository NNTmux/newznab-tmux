<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/releases.php");

/**
 * This class handles data access for groups.
 */
class Groups
{
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
		$db = new DB();

		return $db->query(sprintf("SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
							FROM groups
							LEFT OUTER JOIN
							( SELECT groupID, COUNT(ID) AS num FROM releases group by groupID ) rel ON rel.groupID = groups.ID
							ORDER BY %s", $orderby
			)
		);
	}

	/**
	 * Get all group rows for use in a select list.
	 */
	public function getGroupsForSelect()
	{
		$db = new DB();
		$categories = $db->query("SELECT * FROM groups WHERE active = 1 ORDER BY name");
		$temp_array = array();

		$temp_array[-1] = "--Please Select--";

		foreach ($categories as $category)
			$temp_array[$category["name"]] = $category["name"];

		return $temp_array;
	}

	/**
	 * Get a group row by its ID.
	 */
	public function getByID($id)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("select * from groups where ID = %d ", $id));
	}

	/**
	 * Get all active group rows.
	 */
	public function getActive()
	{
		$db = new DB();

		return $db->query("SELECT * FROM groups WHERE active = 1 ORDER BY name");
	}

	/**
	 * Get a group row by name.
	 */
	public function getByName($grp)
	{
		$db = new DB();

		return $db->queryOneRow(sprintf("SELECT * FROM groups WHERE name = %s", $db->escapeString($grp)));
	}

	/**
	 * Get a group name using its ID.
	 *
	 * @param int|string $id The group ID.
	 *
	 * @return string Empty string on failure, groupName on success.
	 */
	public function getByNameByID($id)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT name FROM groups WHERE ID = %d ", $id));
		return ($res === false ? '' : $res["name"]);
	}

	/**
	 * Get a group name using its name.
	 *
	 * @param string $name The group name.
	 *
	 * @return string Empty string on failure, group_id on success.
	 */
	public function getIDByName($name)
	{
		$db = new DB();
		$res = $db->queryOneRow(sprintf("SELECT ID FROM groups WHERE name = %s", $db->escapeString($name)));
		return ($res === false ? '' : $res["ID"]);
	}

	/**
	 * Get count of all groups, filter by name.
	 */
	public function getCount($groupname = "", $activeonly = false)
	{
		$db = new DB();

		$grpsql = '';
		if ($groupname != "")
			$grpsql .= sprintf("and groups.name like %s ", $db->escapeString("%" . $groupname . "%"));

		if ($activeonly == true)
			$grpsql .= "and active=1 ";

		$res = $db->queryOneRow(sprintf("select count(ID) as num from groups where 1=1 %s", $grpsql));

		return $res["num"];
	}

	/**
	 * Get groups rows for browse list by limit.
	 */
	public function getRange($start, $num, $groupname = "", $activeonly = false)
	{
		$db = new DB();
		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT " . $start . "," . $num;

		$grpsql = '';
		if ($groupname != "")
			$grpsql .= sprintf("and groups.name like %s ", $db->escapeString("%" . $groupname . "%"));
		if ($activeonly == true)
			$grpsql .= "and active=1 ";

		$sql = sprintf("SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
							FROM groups
							LEFT OUTER JOIN
							(
							SELECT groupID, COUNT(ID) AS num FROM releases group by groupID
							) rel ON rel.groupID = groups.ID WHERE 1=1 %s ORDER BY groups.name " . $limit, $grpsql
		);

		return $db->query($sql);
	}

	/**
	 * Add a new group row.
	 */
	public function add($group)
	{
		$db = new DB();

		if ($group["minfilestoformrelease"] == "" || $group["minfilestoformrelease"] == "0")
			$minfiles = 'null';
		else
			$minfiles = $group["minfilestoformrelease"] + 0;

		if ($group["minsizetoformrelease"] == "" || $group["minsizetoformrelease"] == "0")
			$minsizetoformrelease = 'null';
		else
			$minsizetoformrelease = $db->escapeString($group["minsizetoformrelease"]);

		if ($group["backfill_target"] == "" || $group["backfill_target"] == "0")
			$backfill_target = '0';
		else
			$backfill_target = $group["backfill_target"] + 0;

		if ($group["regexmatchonly"] == "" || $group["regexmatchonly"] == "0")
			$regexmatchonly = '0';
		else
			$regexmatchonly = $group["regexmatchonly"] + 0;

		$first = (isset($group["first_record"]) ? $group["first_record"] : "0");
		$last = (isset($group["last_record"]) ? $group["last_record"] : "0");

		$sql = sprintf("insert into groups (name, description, first_record, last_record, last_updated, active, backfill, minfilestoformrelease, minsizetoformrelease, backfill_target, regexmatchonly) values (%s, %s, %s, %s, null, %d, %s, %s, %d, %d) ", $db->escapeString($group["name"]), $db->escapeString($group["description"]), $db->escapeString($first), $db->escapeString($last), $group["active"], $group["backfill"], $minfiles, $minsizetoformrelease, $backfill_target, $regexmatchonly);

		return $db->queryInsert($sql);
	}

	/**
	 * Delete a group.
	 */
	public function delete($id)
	{
		$db = new DB();

		return $db->queryExec(sprintf("DELETE from groups where ID = %d", $id));
	}

	/**
	 * Reset all stats about a group, like its first_record.
	 */
	public function reset($id)
	{
		$db = new DB();

		return $db->queryExec(sprintf("update groups set backfill_target=0, first_record=0, first_record_postdate=null, last_record=0, last_record_postdate=null, last_updated=null where ID = %d", $id));
	}

	/**
	 * Reset all stats about a group and delete all releases and binaries associated with that group.
	 */
	public function purge($id)
	{
		require_once(WWW_DIR . "/lib/binaries.php");

		$db = new DB();
		$releases = new Releases();
		$binaries = new Binaries();

		$this->reset($id);

		$rels = $db->query(sprintf("select ID from releases where groupID = %d", $id));
		foreach ($rels as $rel)
			$releases->delete($rel["ID"]);

		$bins = $db->query(sprintf("select ID from binaries where groupID = %d", $id));
		foreach ($bins as $bin)
			$binaries->delete($bin["ID"]);
	}

	/**
	 * Update a group row.
	 */
	public function update($group)
	{
		$db = new DB();

		if ($group["minfilestoformrelease"] == "" || $group["minfilestoformrelease"] == "0")
			$minfiles = 'null';
		else
			$minfiles = $group["minfilestoformrelease"] + 0;

		if ($group["minsizetoformrelease"] == "" || $group["minsizetoformrelease"] == "0")
			$minsizetoformrelease = 'null';
		else
			$minsizetoformrelease = $db->escapeString($group["minsizetoformrelease"]);

		return $db->queryExec(sprintf("update groups set name = %s, description = %s, backfill_target = %s , active=%d, backfill = %s, minfilestoformrelease=%s, minsizetoformrelease=%s, regexmatchonly=%d where ID = %d ", $db->escapeString($group["name"]), $db->escapeString($group["description"]), $db->escapeString($group["backfill_target"]), $group["active"], $group["backfill"], $minfiles, $minsizetoformrelease, $group["regexmatchonly"], $group["id"]));
	}

	/**
	 * Update the list of newsgroups from nntp provider matching a regex and return an array of messages.
	 */
	function addBulk($groupList, $active = 1, $backfill = 1)
	{
		require_once(WWW_DIR . "/lib/binaries.php");
		require_once(WWW_DIR . "/lib/nntp.php");

		$ret = array();

		if ($groupList == "") {
			$ret[] = "No group list provided.";
		} else {
			$db = new DB();
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
					$res = $db->queryOneRow(sprintf("SELECT ID FROM groups WHERE name = %s ", $db->escapeString($group['group'])));
					if ($res) {

						$db->queryExec(sprintf("update groups SET active = %d where ID = %d", $active, $res["ID"]));
						$ret[] = array('group' => $group['group'], 'msg' => 'Updated');
					} else {
						$desc = "";
						$db->queryInsert(sprintf("INSERT INTO groups (name, description, active, backfill) VALUES (%s, %s, %d, %s)", $db->escapeString($group['group']), $db->escapeString($desc), $active, $backfill));
						$ret[] = array('group' => $group['group'], 'msg' => 'Created');
					}
				}
			}
		}

		return $ret;
	}

	/**
	 * Update a group to be active/inactive.
	 */
	public function updateGroupStatus($id, $status = 0)
	{
		$db = new DB();
		$db->queryExec(sprintf("update groups SET active = %d WHERE ID = %d", $status, $id));
		$status = ($status == 0) ? 'deactivated' : 'activated';

		return "Group $id has been $status.";
	}

	/**
	 * @param     $id
	 * @param int $status
	 *
	 * @return string
	 */
	public function updateBackfillStatus($id, $status = 0)
	{
		$db = new DB();
		$db->queryExec(sprintf("UPDATE groups SET backfill = %d WHERE ID = %d", $status, $id));
		return "Group $id has been " . (($status == 0) ? 'deactivated' : 'activated') . '.';
	}

	/**
	 * @return array
	 */
	public function getActiveBackfill()
	{
		$db = new DB();
		return $db->query("SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY name");
	}

	/**
	 * @return array
	 */
	public function getActiveByDateBackfill()
	{
		$db = new DB();
		return $db->query("SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY first_record_postdate DESC");
	}

	/**
	 * @var array
	 */
	private $cbppTableNames;

	/**
	 * Get the names of the binaries/parts/part repair tables.
	 * If TPG is on, try to create new tables for the group_id, if we fail, log the error and exit.
	 *
	 * @param bool $tpgSetting false, tpg is off in site setting, true tpg is on in site setting.
	 * @param int  $groupID    ID of the group.
	 *
	 * @return array The table names.
	 */
	public function getCBPTableNames($tpgSetting, $groupID)
	{
		$groupKey = ($groupID);

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
				exit('Error: You must use releases_threaded.py since you have enabled TPG!');
			}

			if ($this->createNewTPGTables($groupID) === false && NN_ECHOCLI) {
				exit('There is a problem creating new TPG tables for this group ID: ' . $groupID . PHP_EOL);
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
		$db = new DB();
		return $db->query("SELECT ID FROM groups WHERE active = 1 ORDER BY name");
	}

	/**
	 * Set the backfill to 0 when the group is backfilled to max.
	 *
	 * @param $name
	 */
	public function disableForPost($name)
	{
		$db = new DB();
		$db->queryExec(sprintf("UPDATE groups SET backfill = 0 WHERE name = %s", $db->escapeString($name)));
	}
}