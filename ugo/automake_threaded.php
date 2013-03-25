<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
require(dirname(__FILE__)."/../../../../../www/config.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."/lib/powerprocess.php");
require_once(WWW_DIR."/lib/framework/db.php");

$time = microtime(true);
$db = new DB();

if (isset($argv[1]))
{
//var_dump($argv);

	if ($argv[1] == "reset")
	{
		echo "resetting binaries\n";
		$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat not in (4, 6)");
		echo "binaries have been reset\n";
	}
}

$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat = -6");
$query = "SELECT ID FROM binaries WHERE procstat = 0 GROUP BY binaryhash order by ID";

echo "Getting work to be done\n";
$theList = $db->query($query);
unset($db);

$theCount = count($theList);

$ps = new PowerProcess(4,0,false);
$ps->RegisterCallback('psUpdateComplete');
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use

$tim = microtime(true) - $time;
echo "Query time = ".$tim."\n";

echo "Starting threaded assembly process\n";
echo "\nToday will be doing $theCount binaries\n";

echo "      0\t";

while ($ps->RunControlCode())
{
	$listcount = count($theList);
	// Start the parent loop
	if ($listcount)
	{
		// We still have groups to process
		if ($ps->SpawnReady())
		{
			// Spawn another thread
			$ps->threadData = array_pop($theList);
//			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($theList) ." post(s) to update after this\n";
			$ps->spawnThread();
		}
		else
		{
			// There are no more slots available to run
			//$ps->tick();
			//echo "\nNo more threads available";
		}
	}
	else
	{
		// No more groups to process
		echo "\nNo more posts to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";
	}
}
//$tim = microtime(true) - $time;
// echo "time = ".$tim."\n";
unset($theList);

if ($ps->RunThreadCode())
{
	$post = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

//	echo "[Thread-{$thread}] Begining assembly processing for post {$post['ID']}\n";

	$param = $post['ID'];

	$dir = dirname(__FILE__);
	$file = 'automake.php';

	$output = shell_exec("php {$dir}/{$file} {$param}");

	$count = $theCount - $listcount;
	$countpct = $count / $theCount;
	$cleanpct = str_pad(number_format(($countpct * 100), 1), 7, " ", STR_PAD_LEFT);
	if ($count % 200 == 0 && $count > 0)
	{
		//$ps->tickCount = get_ticks();
		$tim = microtime(true) - $time;
		echo "\t".$cleanpct."% = ".str_pad(number_format($tim, 1), 8, " ", STR_PAD_LEFT);
		$tim = number_format(($listcount * $tim) / 60 / $count, 1);
		if ($countpct > .95 || ($count > 1000 && ($count-200) % 1000 == 0))
			echo "\t".$tim."m to go" ;
		echo "\n".str_pad($count,7, " ", STR_PAD_LEFT)."\t";
	} elseif (($count % 4 == 0) && ($count != 0)) {
		echo ".";
        }
//	echo "[Thread-{$thread}] Completed update for post {$post['ID']}\n";
//	$tim = microtime(true) - $time;
//	echo "time = ".$tim."\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "[Thread-MASTER] Threaded assembly process complete\n";
}



?>

