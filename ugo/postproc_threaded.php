<?php
require("config.php");
//define('FS_ROOT', realpath(dirname(__FILE__)));
//require_once(FS_ROOT."/config.php");

require_once(WWW_DIR.'/lib/powerprocess.php');
require_once(WWW_DIR."/lib/framework/db.php");

function processchanges ()
{
	global $ps, $buffer;

	if (count($ps->myThreads) < 1)
		return false;

	$ps1 = shm_get_var($buffer, 1);
	$change = false;

	$ps1c = count($ps1->myThreads);
	$psc = count($ps->myThreads);

	foreach($ps->myThreads as $k => $v)
	{
		if (isset($ps1->myThreads[$k]))
		{
			if ($v['time'] != $ps1->myThreads[$k]['time'])
			{
				$q = (strval($ps1->myThreads[$k]['time']) - strval($ps->myThreads[$k]['time']));
//				echo "time changed for $q\n";
				$ps->myThreads[$k]['time'] = $ps1->myThreads[$k]['time'];
				$change = true;
			}
		} else {
//			echo "ps not current $psc $ps1c\n";
			$change = true;
		}
	}

	if ($change)
		shm_put_var($buffer, 1, $ps);

//	echo "pause ";
//	var_dump($ps->myThreads);
}

$time = microtime(true);
$db = new DB();

$maxattemptstocheckpassworded = 10;
$numtoProcess = 1000;

$query = sprintf("select r.ID, r.guid, r.name, c.disablepreview from releases r
	left join category c on c.ID = r.categoryID
	where (r.passwordstatus between %d and -1)
	or (r.haspreview = -1 and c.disablepreview = 0) order by RAND() limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess);

$theList = $db->query($query);
unset($db);

$theCount = count($theList);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 16;
$ps->tickCount = 100000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 250;	// Disable child timeout


$handle = ftok(__FILE__, chr(1));
$buffer = shm_attach($handle, 10240);

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";

echo "Starting threaded rar inspection process\n";
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
			processchanges();
		}
		else
		{
			//There are no more slots available to run
			$ps->tick();
			//echo ".\n";
			processchanges();
		}
	}
	else
	{
		// No more groups to process
		echo "No more release to inspect - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";
//		var_dump($ps->myThreads);
		$tim = microtime(true) - $time;
		 echo "final time = ".$tim."\n";
	}
}
$tim = microtime(true) - $time;
// echo "time = ".$tim."\n";
unset($theList);

if ($ps->RunThreadCode())
{
	$release = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

//	echo "[Thread-{$thread}] Begining rar inspection for  {$release['name']} {$handle}  id= {$param}\n";

	$param = $release['ID'];

	$dir = dirname(__FILE__);
	$file = 'postproc.php';

//echo "start ";
//var_dump($ps->myThreads);

	$output = shell_exec("php {$dir}/{$file} {$param} {$handle} {$thread}");

	$count = $theCount - $listcount;
	$countpct = $count / $theCount;

	if ($listcount % 50 == 0 && $listcount > 0)
	{
		$tim = microtime(true) - $time;
		echo "\t\tt = ".str_pad(number_format($tim, 1), 8, " ", STR_PAD_LEFT);
		$tim = ($listcount * $tim) / $count + time();
//		$tim = number_format(($listcount * $tim) / 60 / $count, 1);
		if ($countpct > 0.1 || $count > 1500)
			echo "\t".date('H:i:s', $tim);
//			echo "\t".$tim."m to go" ;
		echo "\n".str_pad($listcount, 7, " ", STR_PAD_LEFT)."\t";

	}
	if ($listcount % 2 == 0)
		echo " .";

//	echo "[Thread-{$thread}] Completed update for binary {$release['name']}\n";
	$tim = microtime(true) - $time;
//	echo "time = ".$tim."\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "[Thread-MASTER] Threaded inspection process complete\n";
}

?>
