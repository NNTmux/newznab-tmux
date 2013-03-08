<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/site.php');
require('lib/tvrage.php');

$s = new Sites();
$site = $s->get();

if ( $site->lookuptvrage == 1)
{
    $tvrage = new TVRage(true);
    $tvrage->processTvReleases(($site->lookuptvrage==1));
}

if ( $site->lookupthetvdb == 1)
{
    $thetvdb = new TheTVDB(true);
    $thetvdb->processReleases();
}

?>

