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

echo "cleaning spam..... can take a few moments\n";

//$db->disableAutoCommit();
//$db->disableForeignKeyChecks();

$rel = $db->query("SELECT `ID` from  `binaries` WHERE  `xref` REGEXP '(.+:[0-9]+ ){5,}' AND `procstat` = 0");

foreach($rel as $r)
{
	$db->query("UPDATE `binaries` set `procstat` = -2 WHERE `ID` = ".$r['ID']);
	if ($db->getLastError() != '')
		var_dump($db->getLastError());
}

echo "spam count ".count($rel)."\n";

$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat = -6");

echo "Getting work to be done\n";
$query = "SELECT ID FROM binaries WHERE `procstat` = 0 GROUP BY binaryhash order by RAND()";

$theList = $db->query($query);
unset($db);

$theCount = count($theList);

//get system load
function get_load() {
        $load = sys_getloadavg();
        return $load[0];
}

$ps = new PowerProcess(4,0,false);
$ps->RegisterCallback('psUpdateComplete');
$ps->maxChildren = 4;
$ps->tickCount = 10000;	// value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0;	// Disable child timeout

$tim = microtime(true) - $time;
echo "time = ".$tim."\n";

echo "Starting threaded assembly process\n";
echo "\nToday will be doing $theCount binaries\n";
echo str_pad($theCount, 7, " ", STR_PAD_LEFT)."\t";

while ($ps->RunControlCode())
{
	// Start the parent loop
	$listcount = count($theList);
	if ($listcount)
	{
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

		// We still have groups to process
		if ( $array['MAX_LOAD_RELEASES'] >= get_load()) {
			if ($ps->SpawnReady())
			{
				// Spawn another thread
				$ps->threadData = array_pop($theList);
	//			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($theList) ." post(s) to update after this\n";
				$ps->spawnThread();
			} //else {
				// There are no more slots available to run
				//$ps->tick();
				//echo "\nNo available threads.\n";
			//}
		} //else {
                	//echo "\nLoad exceeded. ".get_load()."\n";
                //}
	} else 	{
		// No more groups to process
		echo "\nNo more posts to process - Initiating shutdown\n";
		$ps->threadTimeLimit = 200;
		do {
			$ps->tick();
		} while ($ps->myThreads);
		$ps->Shutdown();
		echo "Shutdown complete\n";
		$tim = microtime(true) - $time;
		echo "final time = ".$tim."\n";
	}
}

$tim = microtime(true) - $time;
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

	if ($listcount % 250 == 0 && $listcount > 0)
	{
		$tim = microtime(true) - $time;
		echo "\t\t".$cleanpct."% = ".str_pad(number_format($tim, 1), 8, " ", STR_PAD_LEFT);
		$tim = ($listcount * $tim) / $count + time();
//		$tim = number_format(($listcount * $tim) / 60 / $count, 1);
		if ($countpct > .95 || ($count > 1000 && ($listcount+250) % 1000 == 0))
			echo "\t".date('M jS Y H:i:s', $tim);
//			echo "\t".$tim."m to go" ;
		echo "\n".str_pad($listcount, 7, " ", STR_PAD_LEFT)."\t";

	} elseif ($listcount % 10 == 0)
		echo ".";


//	echo "[Thread-{$thread}] Completed update for post {$post['ID']}\n";
	$tim = microtime(true) - $time;
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

