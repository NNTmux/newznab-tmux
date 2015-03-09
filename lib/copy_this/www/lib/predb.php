<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/sphinx.php");

/**
 * This class handles lookup of nzpre lookups and storage/retrieval of pre data.
 */
class PreDB
{
	/**
	 * Default constructor.
	 */
	function PreDB($echooutput=true)
	{
		$this->echooutput = $echooutput;
	}

	/**
	 * Get a predb row by id.
	 */
	public function getByID($preID)
	{
		$db = new DB();
		$predbQuery = $db->query(sprintf("SELECT * FROM predb WHERE id = %s LIMIT %d", $preID, 1));

		return isset($predbQuery[0]) ? $predbQuery[0] : false;
	}

	/**
	 * Get a predb row by name.
	 */
	public function getByDirname($dirname)
	{
		$db = new DB();
		$dirname = str_replace(' ', '_', $dirname);
		$predbQuery = $db->query(sprintf("SELECT * FROM predb WHERE dirname = %s LIMIT %d", $db->escapeString($dirname), 1));

		return isset($predbQuery[0]) ? $predbQuery[0] : false;
	}

	/**
	 * Get count of all predb rows.
	 */
	public function getPreCount($dirname='', $category='')
	{
	    // Only use Sphinx if this is a search request
	    if ($dirname) {
	        $s = new Sites();
    		$site = $s->get();
    		if ($site->sphinxenabled && $site->sphinxindexpredb) {
    		    // Search using Sphinx
    		    $sphinx = new Sphinx();
    		    $count = $sphinx->getPreCount($dirname, $category);
    		    if ($count > -1) {
    		        return $count;
    		    }
    		}
	    }

		$db = new DB();

		$dirname = empty($dirname) ? '' : sprintf("WHERE dirname LIKE %s", $db->escapeString('%'.$dirname.'%'));
		$category = empty($category) ? '' : sprintf((empty($dirname) ? 'WHERE' : ' AND')." category = %s", $db->escapeString($category));

		$predbQuery = $db->queryOneRow(sprintf('SELECT COUNT(id) AS num FROM predb %s %s', $dirname, $category), true);

		return $predbQuery['num'];
	}

	/**
	 * Get predb rows by limit and filter.
	 */
	public function getPreRange($start=0, $num, $dirname='', $category='')
	{
	    // Only use Sphinx if this is a search request
	    if ($dirname) {
	        $s = new Sites();
    		$site = $s->get();
			if ($site->sphinxenabled && $site->sphinxindexpredb) {
    		    // Search using Sphinx
    		    $sphinx = new Sphinx();
    		    $results = $sphinx->getPreRange($start, $num, $dirname, $category);
           		if (is_array($results)) {
           		    return $results;
           		}
    		}
		}

		$db = new DB();

		$dirname = str_replace(' ', '%', $dirname);
		$dirname = empty($dirname) ? '' : sprintf('WHERE dirname LIKE %s', $db->escapeString('%'.$dirname.'%'));
		$category = empty($category) ? '' : sprintf((empty($dirname) ? 'WHERE' : ' AND')." category = %s", $db->escapeString($category));

		$sql = sprintf('SELECT p.*, r.guid FROM predb p left outer join releases r on p.id = r.preid %s %s ORDER BY ctime DESC LIMIT %d,%d', $dirname, $category, $start, $num);

		return $db->query($sql, true);
	}

	/**
	 * Process all untagged releases to see if they are found in predb.
	 */
	public function processReleases($daysback = 3)
	{
		$db = new DB();

		if ($this->echooutput)
			echo "Predb   : Updating releases with pre data\n";

		$matched = 0;
		$releasesQuery = $db->queryDirect(sprintf('SELECT id, searchname FROM releases WHERE preid IS NULL AND adddate > DATE_SUB(NOW(), INTERVAL %d DAY)', $daysback));
		while($arr = $db->getAssocArray($releasesQuery))
		{
			$arr['searchname'] = str_replace(' ', '_', $arr['searchname']);
			$sql = sprintf("SELECT id FROM predb WHERE dirname = %s LIMIT 1", $db->escapeString($arr['searchname']));
			$predbQuery = $db->queryOneRow($sql);

			if($predbQuery)
			{
				$db->queryExec(sprintf('UPDATE releases SET preid = %d WHERE id = %d', $predbQuery['id'], $arr['id']));

				$matched++;
			}
		}

		if($this->echooutput)
			echo "Predb   : Matched pre data to ".$matched." releases\n";

	}

	/**
	 * Add/Update predb row.
	 */
	public function updatePreDB($db, $preArray)
	{
		if(!preg_match('/^(UN)?((MOD)?NUKED?|DELPRE)$/', $preArray['category']))
		{
			$db->queryExec(sprintf('INSERT INTO predb
				(ctime, dirname, category, filesize, filecount, filename)
				VALUES (%d, %s, %s, %F, %d, %s)
				ON DUPLICATE KEY UPDATE
				category=%3$s, filesize=%4$F, filecount=%5$d, filename=%6$s',
				$preArray['ctime'],
				$db->escapeString($preArray['dirname']),
				$db->escapeString($preArray['category']),
				(!empty($preArray['filesize']) ? (float) $preArray['filesize'] : 0),
				(!empty($preArray['filecount']) ? (int) $preArray['filecount'] : 0),
				(!empty($preArray['nuke_filename']) ? $db->escapeString($preArray['nuke_filename']) : '""')
			));

			//$newCheck = mysql_affected_rows();
			//if($this->echooutput && $newCheck > 0)
			//	echo "!PRE: [".date('Y-m-d H:i:s', $preArray['ctime']).'] - [ '.$preArray['dirname'].' ] - ['.$preArray['category']."]\n";

			return true;
		}
		else
		{
			$db->queryExec(sprintf("update predb
				SET nuketype=%s, nukereason=%s, nuketime=%d
				WHERE dirname = %s",
				$db->escapeString($preArray['category']),
				(!empty($preArray['nuke_filename']) ? $db->escapeString($preArray['nuke_filename']) : '""'),
				$preArray['ctime'],
				$db->escapeString($preArray['dirname'])
			));

			//$newCheck = mysql_affected_rows();
			//if($this->echooutput && $newCheck > 0)
			//	echo $preArray['category'].': ['.date('Y-m-d H:i:s', $preArray['ctime']).'] - [ '.$preArray['dirname'].' ] - ['.$preArray['nuke_filename']."]\n";

			return true;
		}

		return false;
	}

	/**
	 * XOR decode a string with a key.
	 */
	function xorDecode($subject, $nzprekey)
	{
		return ($nzprekey ^ base64_decode($subject));
	}

	/**
	 * Parse the nzpre details from the string.
	 */
	private function nzpreParse($msgHeader, $nzprekey)
	{
		preg_match('/^\[(?P<ctime>\d+)\]-\[(?P<category>[^\]]+)\]-\[ (?P<dirname>[^\ ]+) \](?:-\[(?:(?:(?P<filesize>\d+(\.\d+)?)MB)?\/?(?:(?P<filecount>\d+)F)?\/?(?:(?P<nuke_filename>[^\]]+))?\]))?$/', $this->xorDecode($msgHeader, $nzprekey), $fieldArray);

		return empty($fieldArray) ? false : $fieldArray;
	}

	/**
	 * Get nzpre data from usenet and parse.
	 */
	public function nzpreUpdate()
	{
		require_once(WWW_DIR."/lib/nntp.php");

		$s = new Sites();
		$site = $s->get();

		if(empty($site->nzpregroup) || empty($site->nzpresubject) || empty($site->nzpreposter) || empty($site->nzprefield) || empty($site->nzprekey))
			return false;

		if($this->echooutput)
			echo "Predb   : Checking for new pre data ";

		$db = new DB();
		$nntp = new Nntp();

		if(!$nntp->doConnect()) {
            echo "Failed to get NNTP connection\n";
			return false;
		}

		$ret = $groupData = $nntp->selectGroup($site->nzpregroup);
		if($nntp->isError($ret)) {
			echo "Predb   : Error ".$ret->getMessage()."\n";
			return false;
		}

		$ret = $groupMsgs = $nntp->getOverview(($groupData['last']-(!empty($site->nzprearticles) ? $site->nzprearticles : 500)).'-'.$groupData['last']);
		if($nntp->isError($ret)) {
			echo "Predb   : Error ".$ret->getMessage()."\n";
			return false;
		}

		$added_updated = 0;
		$nzprekey = $site->nzprekey;
		while(strlen($nzprekey) < 1024)
			$nzprekey = $nzprekey.$nzprekey;

		$cnt = !empty($site->nzprearticles) ? $site->nzprearticles : 500;
		foreach($groupMsgs as $groupMsg) {
			if ($cnt%50==0 && $cnt != 0 && $this->echooutput)
				echo $cnt."..";
			$cnt--;

			if(preg_match('/^'.$site->nzpresubject.'$/', $groupMsg['Subject']) && preg_match('/^'.$site->nzpreposter.'$/', $groupMsg['From'])) {
				$ret = $msgHeader = $nntp->getHeader($groupMsg['Message-ID']);
				if($nntp->isError($ret))
					continue;

				for($i=0; $i < count($msgHeader); $i++) {
					if(preg_match('/^'.$site->nzprefield.': /', $msgHeader[$i])) {
						if($nzpreParse = $this->nzpreParse(str_replace($site->nzprefield.': ', '', $msgHeader[$i]), $nzprekey))
						{
							if ($this->updatePreDB($db, $nzpreParse))
							{
								$added_updated++;
							}
						}

						break;
					}
				}
			}
		}

		$nntp->disconnect();

		if($this->echooutput)
			echo "\nPredb   : Added/Updated ".$added_updated." records\n";
	}
}
