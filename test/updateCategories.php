<?php
//This script will rerun all releases against Category.php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."lib/category.php");

$db = new DB();		
$category = new Category();
$changedcount = 0;
$rescount = 0;

//
// [1] change for all where the regex isnt aimed at a specific category (affects most releases)
//
$res = $db->queryDirect("select r.id as id, r.searchname as searchname, g.name as groupname, r.categoryID as rcat from releases r, releaseregex rr, groups g where r.regexid=rr.id and g.ID = r.groupID and rr.categoryid is null and r.adddate > (now() - interval 6 hour)");

//
// [2] update for all in a category
//
//$res = $db->queryDirect("select r.id as id, r.searchname as searchname, g.name as groupname, r.categoryID as rcat from releases r inner join groups g on g.ID = r.groupID where r.categoryID in (6010, 6020, 6030, 6040)");

while ($rel = $db->getAssocArray($res))
{
	$rescount++;
	$categoryID = $category->determineCategory($rel['groupname'], $rel['searchname']);
	if (($categoryID != $rel['rcat']) && ($categoryID != '7900'))
	{
		$changedcount ++;
		echo "Changing category for ".$rel['searchname']." New (".$categoryID.") Old (".$rel['rcat'].")\n";
		$db->query(sprintf("UPDATE releases SET categoryID = %d WHERE ID = %d", $categoryID, $rel['id']));
	}
}

echo $rescount." releases \n";
echo $changedcount." releases changed\n";

?>
