<?php

//This script allows you to delete properly all releases which match some criteria
//The nzb, covers and all linked records will be deleted properly.

require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$releases = new Releases();
$db = new Settings();

//
// [1] delete all releases for a group which only has x number of files
//
//$sql = "select * from releases where totalpart = 1 and groupID in (select ID from groups where name = 'alt.binaries.cd.image')";


//
// [2] delete all releases where the only file inside the rars is *.exe and they are not in the PC category
//
/*
$sql = "
select releasefiles.releaseID as ID
from releasefiles
inner join
( select releaseID, count(*) as totnum from releasefiles group by releaseID ) x on x.releaseID = releasefiles.releaseID and x.totnum = 1
inner join releases on releases.ID = releasefiles.releaseID
where releasefiles.name like '%.exe'
and releases.categoryID not in (4000,4010,4020,4030,4040,4050)
group by releasefiles.releaseID ";
*/

//
// [3] delete all releases which contain a file with password.url in its
//
//$sql = "select distinct releasefiles.releaseID from releasefiles where name = 'password.url'";

//
// [4] delete all releases for a poster
//
//$sql = "select ID from releases where fromname = 'PowerBUS@gmail.com (PowerBUS)'";

//
// [5] delete all under a certain amount of completion
//
//$sql = "select ID from releases where completion != 0 and completion != 100 and completion < 99";

//
// [6] all audio which contains a file with .exe or install.bin in
//
//$sql = "select distinct r.ID from releasefiles rf inner join releases r on r.id = rf.releaseID and r.categoryID like '3%' where rf.name like '%.exe' or rf.name = 'install.bin'";

//
// [7] delete all releases for a name
//
//$sql = "select ID from releases where searchname like '%Friday The 13th The Series S03 -enjoy-%'";

//
// [8] delete all releases for a regex
//
//$sql = "select ID from releases where regexID = 1307";

//
// [9] delete all releases under a certain size (100MB) for a category
//
//$sql = "select ID from releases where categoryID like '2%' and size < 104857600";

$rel = $db->query($sql);
echo "about to delete ".count($rel)." release(s)";

foreach ($rel as $r)
{
	$releases->delete($r['ID']);
}
