<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
require(dirname(__FILE__)."/../../../../../www/config.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR.'/lib/powerprocess.php');
require_once(WWW_DIR."/lib/framework/db.php");

$time = microtime(true);
$db = new DB();

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

$query = "SELECT ID FROM binaries WHERE `procstat` = 0 GROUP BY binaryhash order by ID";

$theList = $db->query($query);
$start_count = count($theList);
unset($db);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
//these next 2 settings have been tuned by jonnyboy to be under 2.0 load with nothing else running
$ps->maxChildren = 20;
$ps->tickCount = 20000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0;	// Disable child timeout

//$tim = microtime(true) - $time;
//echo "time = ".$tim."\n";

echo "Starting threaded assembly process\n";

while ($ps->RunControlCode())
{
	// Start the parent loop
	if (count($theList))
	{
		// We still have groups to process
		if ($ps->SpawnReady())
		{
			// Spawn another thread
			$ps->threadData = array_pop($theList);
			$tim = microtime(true) - $time;
			//echo "[Thread-MASTER] Spawning new thread. Still have " . count($theList) ." post(s) remaining. $tim\n";
			if( count($theList) % 20 == 0 ) {
				echo count($theList)."/".$start_count." - $tim\n";
			}
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
		echo "No more posts to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";
	}
}
//$tim = microtime(true) - $time;
//echo "time = ".$tim."\n";
unset($theList);

if ($ps->RunThreadCode())
{
	$post = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

	//echo "[Thread-{$thread}] Begining assembly processing for post {$post['ID']}\n";

	$param = $post['ID'];

	$dir = dirname(__FILE__);
	$file = 'automake.php';

	$output = shell_exec("php {$dir}/{$file} {$param}");

	//echo "[Thread-{$thread}] Completed update for post {$post['ID']}\n";
	//$tim = microtime(true) - $time;
        //echo "[Thread-{$thread}] Completed update for post {$post['ID']} in $tim\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
        $tim = microtime(true) - $time;
	echo "[Thread-MASTER] Threaded assembly process complete in $tim\n";
}
sleep(5);


?>
