<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/site.php');
require(WWW_DIR.'/lib/tvrage.php');

$s = new Sites();
$site = $s->get();

if ( $site->lookupthetvdb == 1)
{
    $thetvdb = new TheTVDB(true);
    $thetvdb->processReleases();
}

?>

