<?php
require_once("config.php");
require_once(WWW_DIR.'/lib/powerprocess.php');
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

$ps = new PowerProcess;
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 20;
$ps->threadTimeLimit = 0;
$selected = $subdir_count-1;

echo "Starting threaded import process\n";

while ($ps->RunControlCode()) 
{
	if ($selected >= 0)
	{
		if ($ps->SpawnReady()) 
		{
			$ps->threadData = $subdirs[$selected]."/";
			$ps->spawnThread();
				} 
	} 
	else 
	{
		$ps->Shutdown();
	}
$selected--;
}

if ($ps->RunThreadCode())
{
	$subdir = $ps->threadData;
	$thread = sprintf("%05d",$ps->GetPID());

	$dir = dirname(__FILE__);
	$file = 'nzb-importonenzb.php';
	$output = shell_exec("php {$dir}/{$file} {$subdir} true");

	echo "[Thread-{$thread}] Completed importing from {$subdir}\n";
}
exit(0);


function psUpdateComplete()
{
	echo "[Thread-MASTER] The threaded import process has completed.\n";
}

?>

