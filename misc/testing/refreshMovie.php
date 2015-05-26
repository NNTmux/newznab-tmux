<?php

require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$movie = new Movie(true);
$db = new Settings();
$movies = array();
$counter = 1;
$sleepsecsbetweenscrape = 1;

//echo out the properties scraped from imdb for an ID
//print_r($movie->fetchImdbProperties("1375666"));

// CASE 1 - UPDATE ALL RECORDS
//$movies = $db->query("SELECT imdbID from movieinfo");

// CASE 2 - UPDATE ALL WITH A BLANK TITLE
//$movies = $db->query("SELECT imdbID from movieinfo where title = ''");

// CASE 3 - UPDATE ALL RECORDS NOT UPDATED IN THE LAST 6 MONTHS
//$movies = $db->query("SELECT imdbID from movieinfo where updateddate < DATE_ADD(NOW(), INTERVAL -6 MONTH)");

// CASE 4 - UPDATE ALL RECORDS WHERE COVER IS EMPTY
//$movies = $db->query("SELECT imdbID from movieinfo where cover = 0");

// CASE 5 - UPDATE ALL WITH NO TRAILER
//$movies = $db->query("SELECT imdbID from movieinfo where trailer is null and tmdbid is not null");


if (count($movies) == 0)
{
    echo "No records selected to update - either uncomment case or no matches found.\n";
    die();
}

echo "Updating ".count($movies)." records - Sleep interval ".$sleepsecsbetweenscrape." second(s)\n";
foreach ($movies as $mov) {
    echo "Updating ".$mov['imdbID']." (".$counter++."/".count($movies).")\n";
	$mov = $movie->updateMovieInfo($mov['imdbID']);
	sleep($sleepsecsbetweenscrape);
}