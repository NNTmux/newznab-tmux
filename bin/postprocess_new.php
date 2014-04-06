<?php

require_once(dirname(__FILE__).'/config.php');
require_once(WWW_DIR.'/lib/postprocess.php');
require_once(WWW_DIR.'/lib/nntp.php')
require_once(WWW_DIR.'/lib/site.php');
require_once(WWW_DIR.'/lib/anidb.php');
require_once(WWW_DIR.'/lib/tvrage.php');
require_once(WWW_DIR.'/lib/thetvdb.php');
require_once(dirname(__FILE__).'/../lib/ColorCLI.php');
require_once(dirname(__FILE__).'/../lib/functions.php');

$c = new ColorCLI();
$s = new Sites();
$site = $s->get();
if (!isset($argv[1])) {
	exit($c->error("You need to set an argument [additional, nfo, movie, tv, games, ebook, music, anime, unwanted, others, spotnab, sharing]."));
}

$postprocess = new PostProcess(true);
if (isset($argv[1]) && $argv[1] === "additional") {
	// Create the connection here and pass, this is for post processing
	$nntp = new NNTP();
    if ($nntp->doConnect() === false)
    {
	$c = new ColorCLI();
	echo $c->error("Unable to connect to usenet.\n");
	return;
    }
	$postprocess->processAdditional();

    $nntp->doQuit();

} else if (isset($argv[1]) && $argv[1] === "nfo"){
    if ( $site->lookupnfo == 1){
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
    }
    else {
      echo $c->info("Nfo lookup disabled in site settings.\n");
        }
} else if (isset($argv[1]) && $argv[1] === "movie"){
    if ( $site->lookupimdb == 1){
	    $postprocess->processMovies();
	echo '.';
    }
    else  {
        echo $c->info("Movie lookup disabled in site settings.\n");
    }
} else if (isset($argv[1]) && $argv[1] === "tv"){
	    if ($site->lookuptvrage == 1)
		{
			$tvrage = new TVRage(true);
			$tvrage->processTvReleases(($site->lookuptvrage==1));
		}
        else {
            echo $c->info("TVRage lookup disabled in site settings.\n");
        }
		if ($site->lookupthetvdb == 1)
		{
			$thetvdb = new TheTVDB(true);
			$thetvdb->processReleases();
		}
        else {
            echo $c->info("TheTVDB lookup disabled in site settings.\n");
        }
} else if (isset($argv[1]) && $argv[1] === "games") {
    if ($site->lookupgames == 1){
        $functions = new Functions();
        $functions -> processGames();
}   else{
        echo $c->info("Games lookup disabled in site settings.\n");
    }
} else if (isset($argv[1]) && $argv[1] === "ebook") {
    if ($site->lookupbooks == 1){
    $postprocess -> processBooks();
    }
    else {
        echo $c->info("Books lookup disabled in site settings.\n");
    }
} else if (isset($argv[1]) && $argv[1] === "music") {
        if ($site->lookupmusic == 1){
                $postprocess -> processMusic();
            }
        else {
            echo $c->info("Music lookup disabled in site settings.\n");
    }
} else if (isset($argv[1]) && $argv[1] === "anime") {
        if ( $site->lookupanidb == 1)
        {
            $anidb = new AniDB(true);
            $anidb->animetitlesUpdate();
            $anidb->processAnimeReleases();
        }
        else
            {
               echo $c->info("AniDB lookup disabled in site settings.\n");
            }
} else if (isset($argv[1]) && $argv[1] === "spotnab") {
                $postprocess -> processSpotnab();
} else if (isset($argv[1]) && $argv[1] === "unwanted") {
                $postprocess -> processUnwanted();
} else if (isset($argv[1]) && $argv[1] === "other") {
                $postprocess -> processOtherMiscCategory();
} else if (isset($argv[1]) && $argv[1] === "sharing") {
                $functions = new Functions();
                $functions -> processSharing($nntp);
}




