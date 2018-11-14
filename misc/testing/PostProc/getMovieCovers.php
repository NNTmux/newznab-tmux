<?php

//This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Movie;
use Blacklight\ColorCLI;
use App\Models\MovieInfo;

$movie = new Movie(['Echo' => true]);
$colorCli = new ColorCLI();

$movies = MovieInfo::query()->where('cover', '=', 0)->orderBy('year')->orderByDesc('id')->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    $colorCli->primary('Updating '.number_format($count).' movie covers.');
    foreach ($movies as $mov) {
        $startTime = microtime(true);
        $mov = $movie->updateMovieInfo($mov['imdbid']);
    }
} else {
    $colorCli->header('No movie covers to update');
}
