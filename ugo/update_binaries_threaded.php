<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."/lib/powerprocess.php");

//$groups = new Groups;
//$groupList = $groups->getActive();

$query = "SELECT * FROM groups WHERE groups.active = 1 ORDER BY ( groups.last_record - groups.first_record ) ASC";

$time = microtime(true);
$db = new DB();
$theList = $db->query($query);

//unset($groups);
unset($db);

$theCount = count($theList);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 15;
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 1500;	// Disable child timeout

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";

echo "Starting threaded binary update process\n";
echo "\nToday will be be inspecting $theCount binaries\n";
echo str_pad($theCount, 7, " ", STR_PAD_LEFT)."\t";

while ($ps->RunControlCode())
{
	// Start the parent loop
	$listcount = count($theList);
	if ($listcount)
	{
		// We still have groups to process
		if ($ps->SpawnReady())
		{
			// Spawn another thread
			$ps->threadData = array_pop($theList);
//			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($theList) ." releases to rummage after this\n";
			$ps->spawnThread();
		}
		else
		{
			// There are no more slots available to run
			$ps->tick();
			//echo "- \n";
		}
	}
	else
	{
		// No more groups to process
		echo "\nNo more groups to process - Initiating shutdown\n";
		do {
			$ps->tick();
		} while ($ps->myThreads);
		$ps->Shutdown();
		echo "\nShutdown complete\n";
				$tim = microtime(true) - $time;
		 echo "final time = ".$tim."\n";
	}
}
$tim = microtime(true) - $time;
// echo "time = ".$tim."\n";
unset($theList);

if ($ps->RunThreadCode())
{
	$group = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

//	echo "[Thread-{$thread}] Begining processing for group {$group['name']}\n";

	$param = $group['name'];

	$dir = dirname(__FILE__);
	$file = 'update_binaries.php';

	$output = shell_exec("php {$dir}/{$file} {$param}");
	//$output = shell_exec("/usr/bin/php -c /etc/php5/cli/php.ini {$dir}/{$file} {$param}");

	$count = $theCount - $listcount;
	$countpct = $count / $theCount;

	if ($listcount % 12 == 0 && $count > 0)
	{
		$tim = microtime(true) - $time;
		echo "\t\tt = ".str_pad(number_format($tim, 1), 8, " ", STR_PAD_LEFT);
		$tim = ((1 + 0.3 * (1 - $countpct)) * ($listcount * $tim) / $count) + time();
//		$tim = number_format(($listcount * $tim) / 60 / $count, 1);
		if ($countpct > 0.1 || $count > 1500)
			echo "\t".date('H:i:s', $tim);
//			echo "\t".$tim."m to go" ;
		echo "\n".str_pad($listcount, 7, " ", STR_PAD_LEFT)."\t";

	}
	if ($listcount % 1 == 0)
		echo " .";


//	echo "[Thread-{$thread}] Completed update for group {$group['name']}\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "Threaded update process complete\n";
}

?>

