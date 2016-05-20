<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\utility\Utility;
use newznab\ReleaseExtra;

$pdo = new Settings();
$re = new ReleaseExtra();

$releases = $pdo->queryExec(sprintf('SELECT r.id as releases_id, re.mediainfo as mediainfo from releases r INNER JOIN releaseextrafull re ON r.id = re.releases_id'));
$total = $releases->rowCount();
$count = 0;

echo $pdo->log->header("Updating Unique IDs for " . number_format($total) . " releases.");

foreach ($releases as $release) {
	$xmlObj = @simplexml_load_string($release['mediainfo']);
	$arrXml = Utility::objectsIntoArray($xmlObj);
	if (isset($arrXml['File']) && isset($arrXml['File']['track'])) {
		foreach ($arrXml['File']['track'] as $track) {
			if (isset($track['@attributes']) && isset($track['@attributes']['type'])) {
				if ($track['@attributes']['type'] == 'General') {
					if (isset($track['Unique_ID'])) {
						if (preg_match('/\d+/', $track['Unique_ID'], $match)){
							$uniqueid = $match[0];
							if($uniqueid > 0) {
								$re->addUID($release['releases_id'], $uniqueid);
								$count++;
							}
						}
					}
				}
			}
		}
	}
	echo "$count / $total\r";
}
echo $pdo->log->primary('Added ' . $count . ' Unique IDs that were not 0');

