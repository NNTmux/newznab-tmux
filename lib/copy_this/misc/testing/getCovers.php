<?php
//This script will update all records in the movieinfo table

define('FS_ROOT', realpath(dirname(__FILE__)));

use newznab\db\DB;

$movie = new Movie(true);

$db = new DB();

$movies = $db->query("SELECT imdbID from movieinfo where cover = 0");

foreach ($movies as $mov) {
	$mov = $movie->updateMovieInfo($mov['imdbID']);
	sleep(1);
}