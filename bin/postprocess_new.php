<?php

require_once(dirname(__FILE__).'/config.php');
require_once(WWW_DIR.'/lib/postprocess.php');
require_once(WWW_DIR.'/lib/nntp.php');
require_once(WWW_DIR.'/lib/site.php');
require_once(WWW_DIR.'/lib/anidb.php');
require_once(WWW_DIR.'/lib/thetvdb.php');
require_once(dirname(__FILE__).'/../lib/ColorCLI.php');
require_once(dirname(__FILE__).'/../lib/TvAnger.php');
require_once(dirname(__FILE__).'/../lib/Pprocess.php');
require_once(dirname(__FILE__) . '/../lib/Info.php');

$c = new ColorCLI();
$s = new Sites();
$site = $s->get();
if (!isset($argv[1])) {
	exit($c->error("You need to set an argument [additional, nfo, movie, tv, games, xxx, ebook, music, anime, unwanted, others, spotnab, sharing]."));
}
$postprocess = new PostProcess(true);
$pprocess = new PProcess(true);
if (isset($argv[1]) && $argv[1] === "additional") {
	// Create the connection here and pass, this is for post processing
	$nntp = new NNTP();
    if ($nntp->doConnect() === false)
    {
	$c = new ColorCLI();
	echo $c->error("Unable to connect to usenet.\n");
	return;
    }
	$pprocess->processAdditional($nntp);
    $nntp->doQuit();
} else if (isset($argv[1]) && $argv[1] === "nfo"){
    // Create the connection here and pass, this is for post processing
	$nntp = new NNTP();
    if ($nntp->doConnect() === false)
    {
	$c = new ColorCLI();
	echo $c->error("Unable to connect to usenet.\n");
	return;
    }
	$postprocess->processNfos();
    $nntp->doQuit();
} else if (isset($argv[1]) && $argv[1] === "movie"){
	    $pprocess->processMovies();
	echo '.';
} else if (isset($argv[1]) && $argv[1] === "tv"){
			$tvrage = new TvAnger(true);
			$thetvdb = new TheTVDB(true);
	$tvrage->processTvReleases(($site->lookuptvrage == 1));
	$thetvdb->processReleases();
} else if (isset($argv[1]) && $argv[1] === "games") {
	$pprocess->processGames();
} else if (isset($argv[1]) && $argv[1] === "xxx") {
	$pprocess->processXXX();
} else if (isset($argv[1]) && $argv[1] === "console") {
	$pprocess->processConsoleGames();
} else if (isset($argv[1]) && $argv[1] === "ebook") {
	$postprocess->processBooks();
} else if (isset($argv[1]) && $argv[1] === "music") {
                $postprocess -> processMusic();
} else if (isset($argv[1]) && $argv[1] === "anime") {
	$anidb = new AniDB(true);
	$anidb->animetitlesUpdate();
	$anidb->processAnimeReleases();
} else if (isset($argv[1]) && $argv[1] === "spotnab") {
                $postprocess -> processSpotnab();
} else if (isset($argv[1]) && $argv[1] === "unwanted") {
                $postprocess -> processUnwanted();
} else if (isset($argv[1]) && $argv[1] === "other") {
                $postprocess -> processOtherMiscCategory();
} else if (isset($argv[1]) && $argv[1] === "sharing") {
                $pprocess -> processSharing($nntp);
}




