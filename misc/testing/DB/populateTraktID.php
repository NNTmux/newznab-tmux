<?php
//This script will update all records in the movieinfo table
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\processing\tv\TraktTv;

$pdo = new DB();
$trakt = new TraktTv(['Settings' => $pdo]);

$mreleases = $pdo->queryDirect(sprintf('SELECT id, imdbid FROM releases WHERE traktid = 0 AND imdbid IS NOT NULL AND imdbid != 0000000 ORDER BY id ASC'));
$treleases = $pdo->queryDirect(sprintf('SELECT id, videos_id FROM releases WHERE traktid = 0 AND videos_id NOT IN(0,-1,-2)'));
$mtotal = $mreleases->rowCount();
$ttotal = $treleases->rowCount();
$mcount = 0;
if ($mtotal > 0) {
	echo $pdo->log->header("Updating Trakt ID for " . number_format($mtotal) . " movies.");
	foreach ($mreleases as $rel) {
		$mcount++;
		$data = $trakt->client->movieSummary('tt' . $rel['imdbid'], 'min');
		if ($data != false) {
			if (isset($data['ids']['trakt'])) {
				$pdo->queryExec(sprintf('UPDATE releases SET traktid = %s WHERE id = %s', $pdo->escapeString($data['ids']['trakt']), $pdo->escapeString($rel['id'])));
				echo $pdo->log->info('Updated ' . $data['title'] . ' with Trakt ID:' . $data['ids']['trakt']);
			}
		}
	}
	echo $pdo->log->header('Updated ' . $mcount . ' movie(s).');
} else {
	echo $pdo->log->info('No movies need updating');
}

$tcount = 0;
if ($ttotal > 0) {
	echo $pdo->log->header("Updating Trakt ID for " . number_format($ttotal) . " shows.");
	foreach ($treleases as $rel) {
		$tcount++;
		$data = $trakt->client->showSummary($rel['rageid'], 'min');
		if ($data != false) {
			if (isset($data['ids']['trakt'])) {
				$pdo->queryExec(sprintf('UPDATE releases SET traktid = %s WHERE id = %s', $pdo->escapeString($data['ids']['trakt']), $pdo->escapeString($rel['id'])));
				echo $pdo->log->info('Updated ' . $data['title'] . ' with Trakt ID:' . $data['ids']['trakt']);
			}
		}
	}
	echo $pdo->log->header('Updated ' . $tcount . ' show(s).');
} else {
	exit($pdo->log->info('No shows need updating'));
}
