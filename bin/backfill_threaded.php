<?php
require("config.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/binaries.php");
require_once(WWW_DIR.'/lib/powerprocess.php');

//Include AlienX's NewzDash Comms Class.
require_once(dirname(__FILE__) . '/../alienx/ndComms.php');

$groups = new Groups;
$groupList = $groups->getActive();
unset($groups);

$ps = new PowerProcess;
$ps->RegisterCallback('psUpdateComplete');
$ps->maxThreads = 10;
$ps->threadTimeLimit = 0;	// Disable child timeout

echo "Starting threaded backfill process\n";

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

unset($groupList);

if ($ps->RunThreadCode()) 
{
	$group = $ps->threadData;
	
	$thread = sprintf("%05d",$ps->GetPID());
	
	echo "[Thread-{$thread}] Begining backfill processing for group {$group['name']}\n";
	
	$param = $group['name'];
	
	$dir = dirname(__FILE__) . '/../../../';
	$file = 'backfill.php';
	
	//NewzDash
	$ndComm = new newzdashComms;
	$ndCommAllowed = false;
	if ( $ndComm->init() )
		$ndCommAllowed = true;
	
	if ( $ndCommAllowed )
	{
		$args[] = null;
		$args[] = "backfill on " . $group['name'];
		$args[] = "started";
		$ndComm->broadcast($args);
	}
	//End Newzdash
	
	$output = shell_exec("php {$dir}/{$file} {$param}");
	
	//Newzdash
	if ( $ndCommAllowed )
	{
		unset($args);
		$args[] = null;
		$args[] = "backfill on " . $group['name'];
		$args[] = "stopped";
		$ndComm->broadcast($args);
	}
	//End Newzdash
	
	echo "[Thread-{$thread}] Completed update for group {$group['name']}\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete() 
{
	echo "[Thread-MASTER] Threaded backfill process complete\n";
}


?>
