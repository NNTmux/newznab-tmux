<?php

//This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Movie;
use Blacklight\ColorCLI;
use App\Models\MovieInfo;
use Blacklight\processing\tv\TraktTv;

$movie = new Movie(['Echo' => true]);

$movies = MovieInfo::query()->where('imdbid', '<>', 0)->where('traktid', '=', 0)->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    echo ColorCLI::primary('Updating '.number_format($count).' movies.');
    foreach ($movies as $mov) {
        $startTime = microtime(true);
        $traktTv = new TraktTv(['Settings' => null]);
        $mov = $traktTv->client->movieSummary('tt'.str_pad($mov['imdbid'], 7, '0', STR_PAD_LEFT), 'full');
        $trakt = $movie->parseTraktTv($mov);
    }
} else {
    echo ColorCLI::header('No movies to update');
}
