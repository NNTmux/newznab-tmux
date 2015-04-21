<?php
//This Script Verifies your System Time vs Myself Time vs PHP Time
define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;
$res="";

$db = new DB();
$res = $db->queryonerow( sprintf("Select now()"));
foreach($res as $time){
echo "Mysql Time Is Now ".$time."\n";}
$res="";
$res = date('r');
echo "PHP Time Is Now ".$res."\n";
$res="";

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
{
	exec("time /t", $res);
	echo "System Time is Now ".$res['0']."\n";
}
else
{
	exec("date", $res);
	echo "System Time is Now ".$res['0']."\n";
}

