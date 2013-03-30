<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
require(dirname(__FILE__)."/../../../../../www/config.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR."/lib/powerprocess.php");
require_once(WWW_DIR."/lib/framework/db.php");

$time = microtime(true);
$db = new DB();

$thecategory = 8000;

if (isset($argv[1]))
{
	$thecategory = substr($argv[1], 0, 1)."000";
}

echo "Starting sorting process for category $thecategory\n";

$query = "SELECT ID FROM category WHERE parentID = $thecategory";  // OR parentID = 2000 OR parentID = 5000

$res = $db->query($query);

$thecategory = $res[0]['ID'];

unset($res[0]);

foreach($res as $r)
	$thecategory = $thecategory.", ".$r['ID'];

$query = "SELECT releases.ID, releases.`name` FROM releases WHERE releases.categoryID IN ( $thecategory )";

$theList = $db->query($query);
unset($db);

$theCount = count($theList);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
$ps->maxChildren = 24;
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0;	// Disable child timeout

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";


echo "\nToday will be going over $theCount releases\n";
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
//			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($theList) ." posts to sort after this\n";
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
		echo "\nNo more to process - Initiating shutdown\n";
		$ps->threadTimeLimit = 200;
		do {
			$ps->tick();
		} while ($ps->myThreads);
		$ps->Shutdown();
		echo "\nShutdown complete\n";
		$tim = microtime(true) - $time;
		echo "final processing time = ".$tim."\n";

	}
}
$tim = microtime(true) - $time;
//echo "time = ".$tim."\n";
unset($theList);

if ($ps->RunThreadCode())
{
        $post = $ps->threadData;

        $thread = sprintf("%05d",$ps->GetPID());

//      echo "[Thread-{$thread}] Begining assembly processing for post {$post['ID']}\n";

        $param = $post['ID'];

        $dir = dirname(__FILE__);
        $file = 'misc_sorter3.php';

        $output = shell_exec("php {$dir}/{$file} {$param}");

        $count = $theCount - $listcount;
        $countpct = $count / $theCount;
        $cleanpct = str_pad(number_format(($countpct * 100), 1), 7, " ", STR_PAD_LEFT);

        if ($listcount % 250 == 0 && $listcount > 0)
        {
                $tim = microtime(true) - $time;
                echo "\t\t".$cleanpct."% = ".str_pad(number_format($tim, 1), 8, " ", STR_PAD_LEFT);
                $tim = ($listcount * $tim) / $count + time();
//              $tim = number_format(($listcount * $tim) / 60 / $count, 1);
                if ($countpct > .95 || ($count > 1000 && ($listcount+250) % 1000 == 0))
                        echo "\t".date('M jS Y H:i:s', $tim);
//                      echo "\t".$tim."m to go" ;
                echo "\n".str_pad($listcount, 7, " ", STR_PAD_LEFT)."\t";

        } elseif ($listcount % 10 == 0)
                echo ".";


//      echo "[Thread-{$thread}] Completed update for post {$post['ID']}\n";
        $tim = microtime(true) - $time;
//      echo "time = ".$tim."\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "[Thread-MASTER] Threaded sorting process complete\n";
}

?>

