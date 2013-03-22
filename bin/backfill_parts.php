<?php
require(dirname(__FILE__)."/config.php");
require_once(WWW_DIR."/lib/backfill.php");
require_once(WWW_DIR."/lib/framework/db.php");

//get variables from config.sh and defaults.sh
$path = dirname(__FILE__);
$varnames = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$varnames .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
$vardata = shell_exec("cat ".$path."/../config.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$vardata .= shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
$varnames = explode("\n", $varnames);
$vardata = explode("\n", $vardata);
$array = array_combine($varnames, $vardata);
unset($array['']);

$_date = $array['KEVIN_DATE'];
$_parts = $array['KEVIN_PARTS'];

echo "Starting kevin123's backfill process\n\n";
	$groupPost = $_parts;
	if (isset($argv[1])) 
	{
		$groupName = $argv[1];
		$db = new DB;
		$query = $db->queryOneRow(sprintf("select name from groups WHERE (first_record_postdate BETWEEN '${_date}' and now()) and (name = '$groupName')")) or die(mysql_error());
		if (isset($query)) 
		{
			$backfill = new Backfill();
			$backfill->backfillPostAllGroups($groupName, $groupPost);
			echo "Backfilling completed.\n";
		} 
		else 
		{ 
			echo "Already have the target post, skipping the group.\n"; 
		}
	} 
	else 
	{
		$groupName = '';
		$backfill = new Backfill();
		$backfill->backfillPostAllGroups($groupName, $groupPost);
	}

?>
