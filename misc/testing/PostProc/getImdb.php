<?php

// This script will update all records in the movieinfo table
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\Movie;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$movie = new Movie(['Echo' => true]);
$colorCli = new ColorCLI;

$movies = $pdo->query('SELECT imdbid FROM movieinfo WHERE tmdbid = 0 ORDER BY id ASC');
if ($movies instanceof Traversable) {
    $count = $movies->rowCount();
    if ($count > 0) {
        $colorCli->header('Updating movie info for '.number_format($count).' movies.');

        foreach ($movies as $mov) {
            $startTime = now()->timestamp;
            $mov = $movie->updateMovieInfo($mov['imdbid']);

            // tmdb limits are 30 per 10 sec, not certain for imdb
            $diff = floor((now()->timestamp - $startTime) * 1000000);
            if (333333 - $diff > 0) {
                echo "sleeping\n";
                usleep(333333 - $diff);
            }
        }
    } else {
        $colorCli->header('No movies to update');
    }
}
