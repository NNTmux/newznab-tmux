<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/releases.php");
require_once(WWW_DIR ."/lib/category.php");


	function getRelease($name)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT count(*) as total FROM prehash WHERE title =  %s", $db->escapeString($name)));
	}


	function AddRelease($name, $date)
	{
		$db = new DB();
		return $db->queryInsert(sprintf("INSERT INTO prehash (title, hash, predate) VALUES (%s, %s, %s)", $db->escapeString($name), $db->escapeString(md5($name)), $db->escapeString($date)));
	}


	function CheckExists()
	{
		$db = new DB();
		return $db->query(sprintf("select 1 from prehash"));
	}
 	function getHashes()
	{
		$db = new DB();
		return $db->query(sprintf("SELECT r.ID, ph.title, g.name FROM releases r join prehash  ph on ph.hash = r.searchname join groups g ON g.ID = r.groupID  WHERE r.categoryid = 8010"));
	}

	function updaterelease($foundName, $id, $groupname)
	{
		$db = new DB();
		$rel = new Releases();
		$cat = new Category();

		$cleanRelName = $rel->cleanReleaseName($foundName);
		$catid = $cat->determineCategory($groupname, $foundName);

		$db->query(sprintf("UPDATE releases SET name = %s,  searchname = %s, categoryID = %d WHERE ID = %d",  $db->escapeString($cleanRelName),  $db->escapeString($cleanRelName), $catid,  $id));

	}

	function hashInit()
	{

		if (CheckExists() !== false)
		{

			$results = getHashes();

			foreach($results as $result)
			{
				echo "Hash Match! Renaming release... ".$result['title']."\n";
				updaterelease($result['title'], $result['ID'], $result['name']);
            }
		}


  }
?>