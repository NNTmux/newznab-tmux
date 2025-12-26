<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Services\FanartTvService;


$fanart = new FanartTvService();


if (! empty($argv[1])) {
    // Test if you can fetch Fanart.TV images

    // Search for a movie/tv
    $moviefanart = $fanart->getMovieFanArt((string) $argv[1]);
    if ($moviefanart) {
        dump($moviefanart);
    } else {
        cli()->error('Error retrieving Fanart.TV data.');
        exit();
    }
} else {
    cli()->error('Invalid arguments. This script requires a number or string (TMDB or IMDb ID.');
    exit();
}
