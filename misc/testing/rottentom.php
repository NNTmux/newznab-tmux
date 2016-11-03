<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\RottenTomato;

$s = new DB();
$rtkey = $s->getSetting('rottentomatokey');
$rt = new RottenTomato($rtkey);

if (isset($rtkey) && $rtkey !='')
{
	$rt = new RottenTomato($rtkey);

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
