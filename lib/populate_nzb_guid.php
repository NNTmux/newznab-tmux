<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once("ColorCLI.php");
require_once("consoletools.php");
require_once("Enzebe.php");
require_once("functions.php");

// This script updates all releases with the guid from the nzb file. Adapted from nZEDb for newznab

$c = new ColorCLI();
if (isset($argv[1])) {
	$del = false;
	if (isset($argv[2])) {
		$del = $argv[2];
	}
	create_guids($argv[1], $del);
} else {
	exit($c->error("\nThis script updates all releases with the guid (md5 hash of the first message-id) from the nzb file.\n\n"
			. "php $argv[0] true         ...: To create missing nzb_guids.\n"
			. "php $argv[0] true delete  ...: To create missing nzb_guids and delete invalid nzbs and releases.\n"));
}

function create_guids($live, $delete = false)
{
	$db = new DB();
	$consoletools = new ConsoleTools();
	$timestart = TIME();
	$relcount = $deleted = 0;
	$c = new ColorCLI();

	if ($live == "true") {
		$relrecs = $db->queryDirect(sprintf("SELECT ID, guid FROM releases WHERE nzbstatus = 1 AND nzb_guid IS NULL ORDER BY ID DESC"));
	} else if ($live == "limited") {
		$relrecs = $db->queryDirect(sprintf("SELECT ID, guid FROM releases WHERE nzbstatus = 1 AND nzb_guid IS NULL ORDER BY ID DESC LIMIT 10000"));
	}
	$total = $relrecs->rowCount();
	if ($total > 0) {
		echo $c->header("Creating nzb_guids for " . number_format($total) . " releases.");
		$functions = new Functions();
		$nzb = new Enzebe();
		$reccnt = 0;
		foreach ($relrecs as $relrec) {
			$reccnt++;
			$nzbpath = $nzb->NZBPath($relrec['guid']);
			if ($nzbpath !== false) {
				$nzbpath = 'compress.zlib://' . $nzbpath;
				$nzbfile = @simplexml_load_file($nzbpath);
				if (!$nzbfile) {
					if (isset($delete) && $delete == 'delete') {
						//echo "\n".$nzb->NZBPath($relrec['guid'])." is not a valid xml, deleting release.\n";
						$functions->fastDelete($relrec['ID'], $relrec['guid']);
						$deleted++;
					}
					continue;
				}
				$binary_names = array();
				foreach ($nzbfile->file as $file) {
					$binary_names[] = $file["subject"];
				}
				if (count($binary_names) == 0) {
					if (isset($delete) && $delete == 'delete') {
						//echo "\n".$nzb->NZBPath($relrec['guid'])." has no binaries, deleting release.\n";
						$functions->fastDelete($relrec['ID'], $relrec['guid']);
						$deleted++;
					}
					continue;
				}

				asort($binary_names);
				$segment = "";
				foreach ($nzbfile->file as $file) {
					if ($file["subject"] == $binary_names[0]) {
						$segment = $file->segments->segment;
						$nzb_guid = md5($segment);

						$db->exec("UPDATE releases set nzb_guid = " . $db->escapestring($nzb_guid) . " WHERE ID = " . $relrec["ID"]);
						$relcount++;
						$consoletools->overWritePrimary("Created: [" . $deleted . "] " . $consoletools->percentString($reccnt, $total) . " Time:" . $consoletools->convertTimer(TIME() - $timestart));
						break;
					}
				}
			} else {
				if (isset($delete) && $delete == 'delete') {
					//echo $c->primary($nzb->NZBPath($relrec['guid']) . " does not have an nzb, deleting.");
					$functions->fastDelete($relrec['ID'], $relrec['guid']);
				}
			}
		}

		if ($relcount > 0) {
			echo "\n";
		}
		echo $c->header("Updated " . $relcount . " release(s). This script ran for " . $consoletools->convertTime(TIME() - $timestart));
	} else {
		echo $c->info('Query time: ' . $consoletools->convertTime(TIME() - $timestart));
		exit($c->info("No releases are missing the guid."));
	}
}