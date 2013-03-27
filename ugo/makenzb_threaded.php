<?php
define('FS_ROOT', realpath(dirname(__FILE__)));
require("config.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR.'/lib/powerprocess.php');
require_once(WWW_DIR."/lib/framework/db.php");

$time = microtime(true);
$db = new DB();

$query = "SELECT *  FROM `binaries` WHERE `procstat` = -3";

$theList = $db->query($query);
unset($db);

$theCount = count($theList);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 20;
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0;	// Disable child timeout

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";

echo "Starting threaded nzb exporter process\n";
echo "\nToday will be doing $theCount binaries\n";
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
			//echo "[Thread-MASTER] Spawning new thread.  Still have " . count($rel) ." nzb(s) to update after this\n";
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
//		$query = "UPDATE `binaries` SET `procstat` = 6 WHERE `procstat` = -3 OR `procstat` = -2";
//		$db = new Db();
//		var_dump($db->query($query));
		echo "No more nzbs to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";


		$path = FS_ROOT."/nzbs/";

		$filestoprocess = glob($path."*.nzb");

		foreach($filestoprocess as $nzbFile)
		{

			$xml = file_get_contents( $nzbFile );

			$matches = preg_split('/\A(\<\?xml[^\r\n]+?\?\>)\s*\R+/iU',  $xml, 0, PREG_SPLIT_DELIM_CAPTURE);

			$name = preg_split('/\.nzb$/i',  $nzbFile, 0, PREG_SPLIT_DELIM_CAPTURE);

			$name = $name[0];

// 			$marker = $matches[0];
//
// 			if (strlen($marker) == 0 || strlen($marker) >100)
// 			{
// 				$marker = $matches[1];
// 			}

			$marker = '';

			foreach ($matches as $m)
			{
				if (preg_match('/^(\<\?xml[^\r\n]+?\?\>)/iU', $m))
				{
		echo "$m\n";
					$marker = $m;
					continue;
				}
			}


echo "marker "."/(".preg_quote($marker)."\s*\R+)/iU\n";

			if (!empty($marker))
			{
				$matches = preg_split('/('.preg_quote($marker).'\s*\R+)/iU',  $xml, 0, PREG_SPLIT_DELIM_CAPTURE);

				if (strlen($matches[0]) == 0)
				{
					unset($matches[0]);
					$matches = array_values($matches);
				}

				echo count($matches)."\n";

				if (count($matches) > 2)
				{
				for ($i = 0; $i < count($matches); $i = $i + 2)
					{
				echo $i."\n";
					$outxml = $matches[$i] .  $matches[$i + 1];
					$fileout = $name . '.' . $i . '.nzb';
					file_put_contents($fileout, $outxml);
					echo $fileout."\n";
					}
				}
			}
		}
		$tim = microtime(true) - $time;
		 echo "final time = ".$tim."\n";
	}
}

$tim = microtime(true) - $time;
// echo "time = ".$tim."\n";
unset($theList);
unset($db);

if ($ps->RunThreadCode())
{
	$bin = $ps->threadData;

	$thread = sprintf("%05d",$ps->GetPID());

//	echo "[Thread-{$thread}] Begining processing for nzb {$bin['ID']}\n";

	$name = $bin['relname'];

	$name = preg_replace('/yenc/iU',' ',$name);

	$name = preg_replace('/\d+?\/\d+?/iU',' ',$name);

	$name = preg_replace('/[\(\)\{\}\[\]]/iU',' ',$name);

	$name = preg_replace('/[\`\'\"\:\/\-\<\>\|]/iU',' ',$name);

	$name = preg_replace('/\.(?!nzb)/iU',' ',$name);

	$name = preg_replace('/  +?/iU',' ',$name);

	$name = preg_replace('/^ | $/iU','',$name);

	$name = preg_replace('/ /','_',$name);

	$id = $bin['ID'];

	if ($name == '')
	{
		$name = $id;
	}

//	echo "\nname: ".$name." \n";

	$dir = dirname(__FILE__);
	$file = 'makenzb.php';

//	echo "php {$dir}/{$file} {$id} {$name}\n";

	$output = shell_exec("php {$dir}/{$file} {$id} {$name}");

	$count = $theCount - $listcount;
	$countpct = $count / $theCount;

	if ($listcount % 100 == 0 && $listcount > 0)
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
	if ($listcount % 10 == 0)
		echo " .";

// 	echo "[Thread-{$thread}] Completed update for {$bin['name']}\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "Threaded export process complete\n";
}

?>
