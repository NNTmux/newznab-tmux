<?php
//This script will rerun all releases against Category.php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\Categorize;

$db = new DB();
$category = new Categorize();
$changedcount = 0;
$rescount = 0;

//
// [1] change for all where the regex isnt aimed at a specific category (affects most releases)
//
//$res = $db->queryDirect("select r.id as id, r.searchname as searchname, g.name as groupname, r.categories_id as rcat from releases r, releaseregex rr, groups g where r.regexid=rr.id and g.ID = r.groupID and rr.categories_id is null");

//
// [2] update for all in a category
//
//$res = $db->queryDirect("select r.id as id, r.searchname as searchname, g.name as groupname, r.categories_id as rcat from releases r inner join groups g on g.ID = r.groupID where r.categories_id in (6010, 6020, 6030, 6040)");

//
// [3] reset all flac with hashed names
//
//$res = $db->queryDirect("select releases.ID as id, searchname as searchname, groups.name as groupname, releases.categories_id as rcat from releases join groups on groups.ID = releases.groupID where length(searchname) = 40 and groups.name like 'alt.binaries.sounds.flac'");

while ($rel = $db->getAssocArray($res))
{
	$rescount++;
	$categoryID = $category->determineCategory($rel['groupname'], $rel['searchname']);
	if (($categoryID != $rel['rcat']) && ($categoryID != '7900'))
	{
		$changedcount ++;
		echo "Changing category for ".$rel['searchname']." New (".$categoryID.") Old (".$rel['rcat'].")\n";
		$db->exec(sprintf("update releases SET categories_id = %d WHERE ID = %d", $categoryID, $rel['id']));
	}
}

echo $rescount." releases \n";
echo $changedcount." releases changed\n";
