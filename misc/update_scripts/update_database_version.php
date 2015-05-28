<?php

require_once("config.php");

use newznab\db\Settings;

$s = new Settings();
$site = $s->get();
$patches = $s->getUnappliedPatches($site);

if (count($patches) == 0)
	echo "Patchn : No patches required applying. At version ".$site->dbversion."\n";
else
{
	echo "Patchn : Database at version ".$site->dbversion."\n";

	foreach ($patches as $patch)
	{
		echo "Patchn : Executing patch ".basename($patch)."\n";

		$db = new Settings();
		$rows = $db->query("select * from site");

		$dbData = file_get_contents($patch);
		//fix to remove BOM in UTF8 files
		$bom = pack("CCC", 0xef, 0xbb, 0xbf);
		if (0 == strncmp($dbData, $bom, 3)) {
			$dbData = substr($dbData, 3);
		}
		$queries = explode(";", $dbData);
		$queries = array_map("trim", $queries);
		foreach($queries as $q) {
            if (strlen($q) > 0)
			    $db->exec($q);
		}
	}
}