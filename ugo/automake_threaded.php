<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
//require_once(FS_ROOT."/../bin/config.php");
require(dirname(__FILE__).'/../../../../../www/config.php');
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."/lib/powerprocess.php");
require_once(WWW_DIR."/lib/framework/db.php");

$time = microtime(true);
$db = new Db;

if (isset($argv[1]))
{
var_dump($argv);

	if ($argv[1] == "reset")
	{
		echo "binaries have been reset\n";
		$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat not in (4, 6)");
	}
}

$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat = -6");

$query = "SELECT count(*), groups.`name`, groups.ID";
$query = $query." FROM binaries INNER JOIN groups ON binaries.groupID = groups.ID";
$query = $query." WHERE binaries.procstat = 0";
$query = $query." GROUP BY groups.ID ORDER BY count(*) asc";

$groupList = $db->query($query);
unset($db);

$ps = new PowerProcess;
$ps->RegisterCallback('psUpdateComplete');
$ps->maxChildren = 8;
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0;	// Disable child timeout

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";

echo "Starting threaded assembly process\n";

while ($ps->RunControlCode())
{
	// Start the parent loop
	if (count($groupList))
	{
		// We still have groups to process
		if ($ps->SpawnReady())
		{
			// Spawn another thread
			$ps->threadData = array_pop($groupList);
			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($groupList) ." group(s) to update after this\n";
			$ps->spawnThread();
		}
		else
		{
			// There are no more slots available to run
			//$ps->tick();
			//echo ".\n";
		}
	}
	else
	{
		// No more groups to process
		echo "No more groups to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";
	}
}
$tim = microtime(true) - $time;
echo "time = ".$tim."\n";
unset($groupList);

if ($ps->RunThreadCode())
{
	$group = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

	echo "[Thread-{$thread}] Begining assembly processing for group {$group['name']}\n";

	$param = $group['ID'];

	$dir = dirname(__FILE__);
	$file = 'automake.php';

	$output = shell_exec("php {$dir}/{$file} {$param}");

	echo "[Thread-{$thread}] Completed update for group {$group['name']}\n";
	$tim = microtime(true) - $time;
	echo "time = ".$tim."\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "[Thread-MASTER] Threaded assembly process complete\n";
}

?>
