<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\libraries\FanartTV;

$fanart = new FanartTV(Settings::settingValue('APIs..fanarttvkey'));

if (! empty($argv[1])) {

    // Test if you can fetch Fanart.TV images

    // Search for a movie/tv
    $moviefanart = $fanart->getMovieFanart((string) $argv[1]);
    if ($moviefanart) {
        dump($moviefanart);
    } else {
        ColorCLI::error('Error retrieving Fanart.TV data.');
        exit();
    }
} else {
    ColorCLI::error('Invalid arguments. This script requires a number or string (TMDB or IMDb ID.');
    exit();
}
