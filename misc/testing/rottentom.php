<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$pdo = new Settings();
$rtkey = $pdo->getSetting('rottentomatokey');

if (isset($rtkey))
{
	$rt = new RottenTomato($pdo->getSetting('rottentomatokey'));

	//print_r($rt->getMoviesBoxOffice());
	//print_r($rt->getMoviesInTheaters());
	//print_r($rt->getOpeningMovies());
	//print_r($rt->getUpcomingMovies());
	//print_r($rt->getNewDvdReleasess());
	//print_r($rt->getMovieInfo("770805418"));
	//print_r($rt->getMovieReviews("770805418"));
	//print_r($rt->getMovieCast("770805418"));
	print_r($rt->movieSearch("moviename"));
}