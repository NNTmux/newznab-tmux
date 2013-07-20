<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/releases.php");
require_once(WWW_DIR ."/lib/category.php");
		
		
	function getNoHash()
	{	
		$db = new DB();
		return $db->query(sprintf("select * from predb where hash is null"));		
	}
	
	function updateHash($id, $hash)
	{
		$db = new DB();
		return $db->query(sprintf("update predb set hash = %s where id = %d",$db->escapeString($hash), $id));		
	}
	
	
 	function getHashes()
	{			
		$db = new DB();
		return $db->query(sprintf("SELECT r.ID, ph.dirname, g.name FROM releases r join predb  ph on ph.hash = r.searchname join groups g ON g.ID = r.groupID WHERE r.categoryid = 8010"));		
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

	//add hashes to all new pre records
	$results = getNoHash();			
	foreach($results as $result) 
	{
		updateHash($result['ID'], md5($result['dirname']));	
	}
			
	//compare with releases
   	if ($results = getHashes());
		{
		foreach($results as $result)

		{
		echo "Hash Match! Renaming release... ".$result['dirname']."\n";
		updaterelease($result['dirname'], $result['ID'], $result['name']);

        }
        }
     if ($results != getHashes());
        {
          echo "No hash matched!\n";
        }
        
?>