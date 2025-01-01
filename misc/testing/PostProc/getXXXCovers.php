<?php

// This script will update all records in the xxxinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\XXX;
use Illuminate\Support\Facades\DB;

$movie = new XXX;
$c = new ColorCLI;

$movies = DB::select('SELECT title FROM xxxinfo WHERE cover = 0');

echo $c->primary('Updating '.number_format(\count($movies)).' XXX movie covers.');
foreach ($movies as $mov) {
    $starttime = now()->timestamp;
    $mov = $movie->updateXXXInfo($mov->title);

    // sleep so that it's not ddos' the site
    $diff = floor((now()->timestamp - $starttime) * 1000000);
    if (333333 - $diff > 0) {
        echo "\nsleeping\n";
        usleep(333333 - $diff);
    }
}
echo "\n";
