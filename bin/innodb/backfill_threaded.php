<?php
require("lib/innodb/config.php");
require_once(dirname(__FILE__)."/lib/groups.php");
require_once(dirname(__FILE__)."/lib/innodb/binaries.php");
require_once(WWW_DIR.'/lib/powerspawn.php');

$groups = new Groups;
$groupList = $groups->getActive();
unset($groups);

$ps = new PowerSpawn;
$ps->setCallback('psUpdateComplete');
$ps->maxChildren = 10;
$ps->timeLimit = 0;	// Disable child timeout

echo "Starting threaded backfill process\n";

while ($ps->runParentCode()) 
{
	// Start the parent loop
	if (count($groupList)) 
	{
		// We still have groups to process
		if ($ps->spawnReady()) 
		{
			// Spawn another thread
			$ps->childData = array_pop($groupList);
			echo "[Thread-MASTER] Spawning new thread.  Still have " . count($groupList) ." group(s) to backfill after this\n";
			$ps->spawnChild();
		} 
		else 
		{
			// There are no more slots available to run
			$ps->tick();
			echo "Still have " . count($groupList) ." group(s) to backfill. \n";
		}
	} 
	else 
	{
		// No more groups to process
		echo "No more groups to backfill - Initiating shutdown\n";
		$ps->shutdown();
		echo "Shutdown complete\n";
	}
}

unset($groupList);

if ($ps->runChildCode()) 
{
	$group = $ps->childData;
	
	$thread = sprintf("%05d",$ps->myPID());
	
	echo "[Thread-{$thread}] Begining backfill processing for group {$group['name']}\n";
	
	$param = $group['name'];
	
	$dir = dirname(__FILE__);
	$file = 'backfill.php';
	
	$output = shell_exec("php {$dir}/{$file} {$param}");
	
	echo "[Thread-{$thread}] Completed backfill for group {$group['name']}\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete() 
{
	echo "[Thread-MASTER] Threaded backfill process complete\n";
}

?>
