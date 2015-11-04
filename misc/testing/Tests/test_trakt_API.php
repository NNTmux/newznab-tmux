<?php

require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\processing\tv\TraktTv;

$c = new newznab\ColorCLI();
$trakt = new TraktTv();

if (!empty($argv[1]) && is_numeric($argv[2]) && is_numeric($argv[3])) {

	// Test if your Trakt API key and configuration are working
	// If it works you should get a printed array of the show/season/episode entered

	$season = (int)$argv[2];
	$episode = (int)$argv[3];

	// Search for a show
	$series = $trakt->client->showSummary((string)$argv[1]);

	// Use the first show found (highest match) and get the requested season/episode from $argv
	if (is_array($series)) {

		$ep = $trakt->client->episodeSummary((int)$series['ids']['trakt'], $season, $episode, 'full');

		print_r($series);
		print_r($ep);

	} else {
		exit($c->error("Error retrieving Trakt data."));
	}
} else {
	exit($c->error("Invalid arguments.  This script requires a text string (show name) followed by a season and episode number."));
}
