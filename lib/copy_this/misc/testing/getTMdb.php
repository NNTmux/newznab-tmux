<?php
// search tmdb or web for movie from a given name

require_once dirname(__FILE__) . '/../../www/config.php';

$moviename = "Africa Screams";
$movie = new Movie();

// #1 tmdb
//
//print_r($movie->searchTmdb($moviename));

// #2 search engine (google/bing)
//$buffer = getUrl("https://www.google.com/search?source=ig&hl=en&rlz=&btnG=Google+Search&aq=f&oq=&q=".urlencode($moviename.' site:imdb.com'));
$buffer = \newznab\utility\Utility::getUrl(['url' => 'http://www.bing.com/search?&q='.urlencode($moviename.' site:imdb.com')]);
if ($buffer !== false && strlen($buffer))
{
    $imdb = $movie->parseImdbFromNfo($buffer);
    echo sprintf("imdbid : %s\n", $imdb);
    print_r($movie->fetchImdbProperties($imdb));
}