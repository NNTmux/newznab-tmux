<?php
//This script will update all records in the movieinfo table

require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\DB;

$movie = new Movie(true);

$db = new DB();

$movies = $db->query("SELECT imdbID from movieinfo where cover = 0");

foreach ($movies as $mov) {
	$mov = $movie->updateMovieInfo($mov['imdbID']);
	sleep(1);
}