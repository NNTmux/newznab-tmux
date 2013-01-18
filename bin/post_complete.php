<?php

require_once("/var/www/newznab/www/config.php");

require_once(WWW_DIR."/lib/postprocess.php");
require_once(WWW_DIR."/lib/framework/db.php");

$db = new DB;
$postprocess = new PostProcess(true);

while (true) {
	$thequery = "SELECT count(r.ID) AS thecount FROM releases r LEFT OUTER JOIN category c ON c.ID = r.categoryID WHERE (r.passwordstatus between -10 and -1) or (r.haspreview = -1 and c.disablepreview = 0)";
	$result = $db->query($thequery); 
	if ($result[0]["thecount"] < 10 ) 
	{
		$postprocess->processAll();
		echo "done...waiting";
		sleep(600);
	} else {
		for ($i = 0; $i < $result[0]["thecount"]; $i=$i+5)
		{
			echo "\n";
			echo $result[0]["thecount"] - $i. " more to go: \n";
			$postprocess->processAdditional();
		}
	}
}
?>
