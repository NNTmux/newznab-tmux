<?php
require("config.php");
require_once(dirname(__FILE__)."/lib/framework/db.php");
require_once(dirname(__FILE__)."/lib/groups.php");
require_once(dirname(__FILE__)."/lib/innodb/binaries.php");
require_once(dirname(__FILE__)."/lib/PowerProcess.class.php");

$groups = new Groups;
$groupList = $groups->getActive();
unset($groups);

$ps = new PowerProcess;
$ps->RegisterCallback('psUpdateComplete');
$ps->SetMaxThreads = 10;
$ps->SetThreadTimeLimit = 0;	// Disable child timeout

echo "Starting threaded binary update process\n";

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
			$ps->SpawnThread();
		}
		else 
		{
			// There are no more slots available to run
			$ps->Tick();
			#echo "- \n";
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
	
	echo "[Thread-{$thread}] Begining processing for group {$group['name']}\n";
	
	$param = $group['name'];
	
	$dir = dirname(__FILE__);
	$file = 'update_binaries.php';
	
	$output = shell_exec("php {$dir}/{$file} {$param}");
	
	echo "[Thread-{$thread}] Completed update for group {$group['name']}\n";
}

// Exit to call back to parent - Let know that child has completed
exit(0);

// Create callback function
function psUpdateComplete() 
{
	echo "[Thread-MASTER] Threaded update process complete\n";
}

?>
