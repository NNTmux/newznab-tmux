<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\ConsoleTools;
use newznab\NNTP;
use newznab\db\Settings;
use newznab\Categorize;
use newznab\Releases;
use newznab\SphinxSearch;

$pdo = new Settings();
$consoletools = new ConsoleTools(['ColorCLI' => $pdo->log]);
$cat = new Categorize(['Settings' => $pdo]);
$releases = new Releases(['Settings' => $pdo]);
$sphinxsearch = new SphinxSearch(['Settings' => $pdo]);

// Backfill settings
$group = 'alt.binaries.erotica';
$limit = 200;
$delay = 3;
$outputOnly = false;

echo $pdo->log->primary("Started backfill request id lookups (remote)\n");

if(isset($argv[1]) && !empty($argv[1]))
{
	$delay = (int)$argv[1];
	echo $pdo->log->primary("Request delay manually set to: " . $delay);
}

if(isset($argv[2]) && !empty($argv[2]))
{
	$outputOnly = $argv[2];
	echo $pdo->log->primary("Output only manually set to: " . $outputOnly);
}

$sql = 	sprintf ('
	SELECT r.id, r.name, r.searchname, r.guid, r.fromname, r.reqid, r.groupid, g.name AS groupname, r.categoryid, r.postdate
	FROM releases r
	INNER JOIN groups g ON r.groupid = g.id
	WHERE r.nzbstatus = 1
	AND (r.searchname = r.reqid OR r.isrequestid = 1)
	AND r.preid = 0
	AND r.isrenamed = 0
	AND  g.name = %s
	ORDER BY r.postdate DESC
	LIMIT %s',
	$pdo->escapeString($group),
	$limit);

$releasesResults = $pdo->queryDirect($sql);

if ($releasesResults instanceof \Traversable) {
	$totalProcessed = 0;
	$totalReleases = $releasesResults->rowCount();

	echo $pdo->log->primary(sprintf(
			"Release count to process: %s",
			$totalReleases
		)
	);

	foreach($releasesResults as $release) {
		$reqid = $release['reqid'];
		$newtitle = $releases->getReleaseNameForReqId($pdo->getSetting('reqidurl'), $pdo->getSetting('newznabID'), 'alt.binaries.erotica', $release['reqid']);

		if(empty($newtitle))
		{
			$newtitle = $releases->getReleaseNameForReqId($pdo->getSetting('reqidurl'), $pdo->getSetting('newznabID'), 'alt.binaries.erotica', $release['name']);
		}

		if(!empty($newtitle) && $newtitle != 'no feed')
		{
			$catId = $cat->determineCategory($release['groupid'], $newtitle);
			$query = sprintf("UPDATE releases SET searchname = %s, categoryID = %s, isrenamed = 1 WHERE guid = %s", $pdo->escapeString($newtitle), $catId, $pdo->escapeString($release['guid']));

			if($outputOnly == false)
			{
				// Update release
				$pdo->queryExec($query);

				// Update Sphinx
				$sphinxsearch->updateRelease($release['id'], $pdo);

				echo $pdo->log->primary(sprintf(
						"Updated release: \nOriginal title: %s\nNew title: %s\nNew category: %s\nPosted: %s\nGUID: %s\n",
						$release['name'], $newtitle, $catId, $release['postdate'], $release['guid']
					)
				);
			}
			else
			{
				echo $pdo->log->primary(sprintf(
						"[Output only] Would have updated release: \nOriginal title: %s\nNew title: %s\nNew category: %s\nGUID: %s\n",
						$release['name'], $newtitle, $catId, $release['guid']
					)
				);
			}
		}

		$totalProcessed++;

		echo $pdo->log->primary(sprintf(
				"Total processed: %s / %s",
				$totalProcessed, $totalReleases
			)
		);

		$newtitle = '';
		$requestId  = '';
		sleep($delay);
	}
}

echo $pdo->log->primary("Completed backfill request id lookups (remote)\n");
