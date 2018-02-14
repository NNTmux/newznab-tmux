<?php

//This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\db\DB;
use Blacklight\Movie;
use Blacklight\ColorCLI;

$pdo = new DB();

$movie = new Movie(['Echo' => true, 'Settings' => $pdo]);

$movies = $pdo->queryDirect('SELECT imdbid FROM movieinfo WHERE cover = 0 ORDER BY year ASC, id DESC');
$count = $movies->rowCount();
if ($count > 0) {
    if ($movies instanceof \Traversable) {
        echo ColorCLI::primary('Updating '.number_format($count).' movie covers.');
        foreach ($movies as $mov) {
            $startTime = microtime(true);
            $mov = $movie->updateMovieInfo($mov['imdbid']);

            // tmdb limits are 30 per 10 sec, not certain for imdb
            $diff = floor((microtime(true) - $startTime) * 1000000);
            if (333333 - $diff > 0) {
                echo "\nsleeping\n";
                usleep(333333 - $diff);
            }
        }
    }
} else {
    echo ColorCLI::header('No movie covers to update');
}
