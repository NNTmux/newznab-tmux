<?php

// This script will update all records in the movieinfo table where there is no cover
require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\MovieInfo;
use App\Services\MovieService;
use App\Services\TvProcessing\Providers\TraktProvider;


$movie = new MovieService();
$movie->echooutput = true;


$movies = MovieInfo::query()->where('imdbid', '<>', 0)->where('traktid', '=', 0)->get(['imdbid']);
$count = $movies->count();
if ($count > 0) {
    cli()->primary('Updating '.number_format($count).' movies for TraktTV id.');
    foreach ($movies as $mov) {
        $traktTv = new TraktProvider();
        $traktmovie = $traktTv->client->getMovieSummary('tt'.$mov['imdbid'], 'full');
        if ($traktmovie !== false) {
            cli()->info('Updating IMDb id: tt'.$mov['imdbid']);
            $trakt = $movie->parseTraktTv($traktmovie);
            if ($trakt === true) {
                cli()->info('Added traktid: '.$traktmovie['ids']['trakt']);
            } else {
                cli()->info('No traktid found.');
            }
        }
    }
} else {
    cli()->header('No movies to update');
}
