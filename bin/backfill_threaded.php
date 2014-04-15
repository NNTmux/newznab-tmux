<?php
require(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . "/lib/binaries.php");
require_once(WWW_DIR . "/lib/powerprocess.php");
$time = TIME();

//Include AlienX's NewzDash Comms Class.
require_once(dirname(__FILE__) . "/../alienx/ndComms.php");

$groups = new Groups;
$groupList = $groups->getActive();
unset($groups);

$ps = new PowerProcess();
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 5;
$ps->tickCount = 500000;
$ps->threadTimeLimit = 0; // Disable child timeout

echo "Starting threaded backfill process\n";

while ($ps->RunControlCode()) {
	// Start the parent loop
	if (count($groupList)) {
		// We still have groups to process
		if ($ps->SpawnReady()) {
			// Spawn another thread
			$ps->threadData = array_pop($groupList);
			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($groupList) . " group(s) to update after this\n";
			$ps->spawnThread();
		} else {
			// There are no more slots available to run
			//$ps->tick();
			//echo ".\n";
		}
	} else {
		// No more groups to process
		echo "No more groups to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "Shutdown complete\n";
		echo "\n\033[1;33m[Thread-MASTER] Threaded stock backfill process completed in: " . relativeTime($time) . "\n";
	}
}

unset($groupList);

if ($ps->RunThreadCode()) {
	$group = $ps->threadData;

	$thread = sprintf("%05d", $ps->GetPID());

	echo "[Thread-{$thread}] Begining backfill processing for group {$group['name']}\n";

	$param = $group['name'];

	$dir = WWW_DIR . '/../misc/update_scripts/';
	$file = 'backfill.php';

	//NewzDash
	$ndComm = new newzdashComms;
	$ndCommAllowed = false;
	if ($ndComm->init())
		$ndCommAllowed = true;

	if ($ndCommAllowed) {
		$args[] = null;
		$args[] = "backfill on " . $group['name'];
		$args[] = "started";
		$ndComm->broadcast($args);
	}
	//End Newzdash

	$output = passthru("cd {$dir}/ && php {$file} {$param}");

	//Newzdash
	if ($ndCommAllowed) {
		unset($args);
		$args[] = null;
		$args[] = "backfill on " . $group['name'];
		$args[] = "stopped";
		$ndComm->broadcast($args);
	}
	//End Newzdash

	echo "[Thread-{$thread}] Completed update for group {$group['name']}\n";
}

function relativeTime($_time)
{
	$d[0] = array(1, "sec");
	$d[1] = array(60, "min");
	$d[2] = array(3600, "hr");
	$d[3] = array(86400, "day");
	$d[4] = array(31104000, "yr");

	$w = array();

	$return = "";
	$now = TIME();
	$diff = ($now - $_time);
	$secondsLeft = $diff;

	for ($i = 4; $i > -1; $i--) {
		$w[$i] = intval($secondsLeft / $d[$i][0]);
		$secondsLeft -= ($w[$i] * $d[$i][0]);
		if ($w[$i] != 0) {
			//$return.= abs($w[$i]). " " . $d[$i][1] . (($w[$i]>1)?'s':'') ." ";
			$return .= $w[$i] . " " . $d[$i][1] . (($w[$i] > 1) ? 's' : '') . " ";
		}
	}

	//$return .= ($diff>0)?"ago":"left";
	return $return;
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete()
{
	echo "\n\033[1;33mThreaded stock backfill process completed in: " . relativeTime($time) . "\n";
}
