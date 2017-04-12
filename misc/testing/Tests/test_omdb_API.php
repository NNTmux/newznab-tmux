<?php

require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use aharen\OMDbAPI;
use nntmux\ColorCLI;

$omdb = new OMDbAPI();

if (!empty($argv[1])) {

	// Test if your OMDb API key and configuration are working
	// If it works you should get a printed array of the show entered

	// Search for a show
	$series = $omdb->search((string)$argv[1], 'series');
	if (is_object($series) && $series->data->Response !== 'False' ) {
		print_r($series);
	}

	// Use the first show found (highest match) and get the requested season/episode from $argv
	if (is_object($series) && $series->data->Response !== 'False') {

		$series = $omdb->fetch('i', $series->data->Search[0]->imdbID);

		print_r($series);


	} else {
		exit(ColorCLI::error('Error retrieving OMDb API data.'));
	}
} else {
	exit(ColorCLI::error('Invalid arguments. This script requires a text string (show name).'));
}
