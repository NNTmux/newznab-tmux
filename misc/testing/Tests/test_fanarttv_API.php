<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Services\FanartTvService;
use Blacklight\ColorCLI;

$fanart = new FanartTvService();
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
