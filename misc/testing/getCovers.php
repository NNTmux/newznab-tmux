<?php
//This script will update all records in the movieinfo table

require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\Movie;

$movie = new Movie(true);

$db = new Settings();

$movies = $db->query("SELECT imdbid from movieinfo where cover = 0");

foreach ($movies as $mov) {
	$mov = $movie->updateMovieInfo($mov['imdbid']);
	sleep(1);
}
