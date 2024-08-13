<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\libraries\FanartTV;

$fanart = new FanartTV(config('nntmux_api.fanarttv_api_key'));
$colorCli = new ColorCLI;

if (! empty($argv[1])) {
    // Test if you can fetch Fanart.TV images

    // Search for a movie/tv
    $moviefanart = $fanart->getMovieFanArt((string) $argv[1]);
    if ($moviefanart) {
        dump($moviefanart);
    } else {
        $colorCli->error('Error retrieving Fanart.TV data.');
        exit();
    }
} else {
    $colorCli->error('Invalid arguments. This script requires a number or string (TMDB or IMDb ID.');
    exit();
}
