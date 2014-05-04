<?php
require(dirname(__FILE__) . "/config.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . "/lib/binaries.php");
require_once(WWW_DIR . "/lib/powerprocess.php");
$time = TIME();

$groups = new Groups;
$groupList = $groups->getActive();
unset($groups);

$ps = new PowerProcess(5, 0, false);
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 5;
$ps->tickCount = 500000; // value in usecs. change this to 1000000 (one second) to reduce cpu use
$ps->threadTimeLimit = 0; // Disable child timeout

echo "Starting kevin123's threaded backfill process\n\n";

while ($ps->RunControlCode()) {
	// Start the parent loop
	if (count($groupList)) {
		// We still have groups to process
		if ($ps->SpawnReady()) {
			// Spawn another thread
			$ps->threadData = array_pop($groupList);
			echo "\n[Thread-MASTER] Spawning new thread.  Still have " . count($groupList) . " group(s) to update after this\n";
			$ps->spawnThread();
		} else {
			// There are no more slots available to run
			//$ps->tick();
			//echo "No threads avaialble.\n";
		}
	} else {
		// No more groups to process
		echo "\nNo more groups to process - Initiating shutdown\n";
		$ps->Shutdown();
		echo "\nShutdown complete\n";
		echo "\n\033[1;33m[Thread-MASTER] Kevin123's threaded backfill parts process completed in: " . relativeTime($time) . "\n";
	}
}

unset($groupList);

if ($ps->RunThreadCode()) {
	$group = $ps->threadData;

	$thread = sprintf("%05d", $ps->GetPID());

	echo "\n[Thread-{$thread}] Begining backfill processing for group {$group['name']}\n";

	$param = $group['name'];

	$dir = dirname(__FILE__);
	$file = 'backfill_parts.php';

	$output = passthru("php {$dir}/{$file} {$param}");
	//$output = shell_exec("/usr/bin/php -c /etc/php5/cli/php.ini {$dir}/{$file} {$param}");

	echo "\n[Thread-{$thread}] Completed update for group {$group['name']}\n";
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
	echo "\n\033[1;33mKevin123's threaded backfill parts process completed in: " . relativeTime($time) . "\n";
}

