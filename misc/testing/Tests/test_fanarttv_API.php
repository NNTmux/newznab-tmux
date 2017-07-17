<?php

require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Models\Settings;
use nntmux\libraries\FanartTV;
use nntmux\ColorCLI;

$fanart = new FanartTV(Settings::value('APIs..fanarttvkey'));

if (!empty($argv[1])) {

	// Test if you can fetch Fanart.TV images

	// Search for a movie/tv
	$moviefanart = $fanart->getMovieFanart((string)$argv[1]);
	if ($moviefanart) {

		print_r($moviefanart);

	} else {
		exit(ColorCLI::error('Error retrieving Fanart.TV data.'));
	}
} else {
	exit(ColorCLI::error('Invalid arguments. This script requires a number or string (TMDB or IMDb ID.'));
}
