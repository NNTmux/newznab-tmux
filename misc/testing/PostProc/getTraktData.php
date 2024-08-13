<?php

//This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\MovieInfo;
use Blacklight\ColorCLI;
use Blacklight\Movie;
use Blacklight\processing\tv\TraktTv;

$movie = new Movie(['Echo' => true]);
$colorCli = new ColorCLI;

$movies = MovieInfo::query()->where('imdbid', '<>', 0)->where('traktid', '=', 0)->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    $colorCli->primary('Updating '.number_format($count).' movies for TraktTV id.');
    foreach ($movies as $mov) {
        $traktTv = new TraktTv(['Settings' => null]);
        $traktmovie = $traktTv->client->movieSummary('tt'.$mov['imdbid'], 'full');
        if ($traktmovie !== false) {
            $colorCli->info('Updating IMDb id: tt'.$mov['imdbid']);
            $trakt = $movie->parseTraktTv($traktmovie);
            if ($trakt === true) {
                $colorCli->info('Added traktid: '.$traktmovie['ids']['trakt']);
            } else {
                $colorCli->info('No traktid found.');
            }
        }
    }
} else {
    $colorCli->header('No movies to update');
}
