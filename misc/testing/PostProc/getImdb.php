<?php

//This script will update all records in the movieinfo table
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Movie;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$movie = new Movie(['Echo' => true]);

$movies = $pdo->query('SELECT imdbid FROM movieinfo WHERE tmdbid = 0 ORDER BY id ASC');
if ($movies instanceof \Traversable) {
    $count = $movies->rowCount();
    if ($count > 0) {
        echo ColorCLI::header('Updating movie info for '.number_format($count).' movies.');

        foreach ($movies as $mov) {
            $startTime = microtime(true);
            $mov = $movie->updateMovieInfo($mov['imdbid']);

            // tmdb limits are 30 per 10 sec, not certain for imdb
            $diff = floor((microtime(true) - $startTime) * 1000000);
            if (333333 - $diff > 0) {
                echo "sleeping\n";
                usleep(333333 - $diff);
            }
        }
    } else {
        echo ColorCLI::header('No movies to update');
    }
}
