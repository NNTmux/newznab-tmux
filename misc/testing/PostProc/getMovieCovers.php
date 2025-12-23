<?php

// This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\MovieInfo;
use App\Services\MovieService;
use Blacklight\ColorCLI;

$movie = new MovieService();
$movie->echooutput = true;
$colorCli = new ColorCLI;

$movies = MovieInfo::query()->where('cover', '=', 0)->orderBy('year')->orderByDesc('id')->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    $colorCli->primary('Updating '.number_format($count).' movie covers.');
    foreach ($movies as $mov) {
        $startTime = now()->timestamp;
        $mov = $movie->updateMovieInfo($mov['imdbid']);
    }
} else {
    $colorCli->header('No movie covers to update');
}
