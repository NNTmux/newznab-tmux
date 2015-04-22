<?php
define('FS_ROOT', realpath(dirname(__FILE__)));

$s = new Sites();
$site = $s->get();

if (isset($site->rottentomatokey))
{
	$rt = new RottenTomato($site->rottentomatokey);

	//print_r(json_decode($rt->getBoxOffice()));
	//print_r(json_decode($rt->getInTheaters()));
	//print_r(json_decode($rt->getOpening()));
	//print_r(json_decode($rt->getUpcoming()));
	//print_r(json_decode($rt->getDVDReleases()));
	//print_r(json_decode($rt->getMovie("770805418")));
	//print_r(json_decode($rt->getReviews("770805418")));
	//print_r(json_decode($rt->getCast("770805418")));
	print_r(json_decode($rt->searchMovie("moviename")));
}