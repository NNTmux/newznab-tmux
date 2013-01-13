<?php
require("lib/innodb/config.php");
require_once(WWW_DIR.'/lib/powerspawn.php');
$dirroot = $argv[1];
$subdirs = array_filter(glob($dirroot."/*"), 'is_dir');
$subdir_count = 0;

foreach($subdirs AS $subdir){
	$filecount = count(glob($subdir."/*.nzb"));
	if($filecount > 0){
		echo "Directory {$subdir} contains {$filecount} nzb's.\n";
		$subdir_count++;
	}
}

if($subdir_count == 0){
	echo "Nothing to import, sub-directories are empty or there are no sub-directories to process.\n";
	die();
}

$ps = new PowerSpawn;
$ps->setCallback('psUpdateComplete');
$ps->maxChildren = 20;
$ps->timeLimit = 0;
$selected = $subdir_count-1;

echo "Starting threaded import process\n";

while ($ps->runParentCode()) 
{
	if ($selected > 0)
	{
		if ($ps->spawnReady()) 
		{
			$ps->childData = $subdirs[$selected]."/";
			$ps->spawnChild();
				} 
	} 
	else 
	{
		$ps->shutdown();
	}
$selected--;
}

if ($ps->runChildCode()) 
{
	$subdir = $ps->childData;
	$thread = sprintf("%05d",$ps->myPID());

	$dir = dirname(__FILE__);
	$file = 'nzb-importonenzb.php';
	$output = shell_exec("php {$dir}/{$file} {$subdir} true");
	
	echo "[Thread-{$thread}] Completed importing from directory {$subdir}.\n";
}

exit(0);

function psUpdateComplete() 
{
	echo "[Thread-MASTER] The threaded import process has completed.\n";
}

?>
