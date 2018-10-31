<?php

//This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Movie;
use Blacklight\ColorCLI;
use App\Models\MovieInfo;
use Blacklight\processing\tv\TraktTv;

$movie = new Movie(['Echo' => true]);
$colorCli = new ColorCLI();

$movies = MovieInfo::query()->where('imdbid', '<>', 0)->where('traktid', '=', 0)->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    $colorCli->primary('Updating '.number_format($count).' movies for TraktTV id.');
    foreach ($movies as $mov) {
        $traktTv = new TraktTv(['Settings' => null]);
        $traktmovie = $traktTv->client->movieSummary('tt'.str_pad($mov['imdbid'], 7, '0', STR_PAD_LEFT), 'full');
        if ($traktmovie !== false) {
            $colorCli->info('Updating IMDb id: tt'.str_pad($mov['imdbid'], 7, '0', STR_PAD_LEFT));
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
