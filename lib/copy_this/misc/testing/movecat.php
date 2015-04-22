<?php

//This script moves releases from one category to another

define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$releases = new Releases();
$db = new DB();

//
// [1] move mp3 files from other misc into audio mp3
//
/*
$sql = "update releases inner join (
select distinct rf.releaseID
from releasefiles rf
inner join releases r on r.ID = rf.releaseID
where rf.name like '%.mp3'
and r.categoryID like '7%') x on x.releaseID = releases.ID
set releases.categoryID = 3010;";
*/



$db->exec($sql);