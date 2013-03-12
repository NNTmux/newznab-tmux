<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/site.php');
require(WWW_DIR.'/lib/anidb.php');

$s = new Sites();
$site = $s->get();

if ( $site->lookupanidb == 1)
{
    $anidb = new AniDB(true);
    $anidb->animetitlesUpdate();
    $anidb->processAnimeReleases();
}

?>

