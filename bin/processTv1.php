<?php

require(dirname(__FILE__)."/config.php");
require(WWW_DIR.'/lib/site.php');
require(WWW_DIR.'/lib/tvrage.php');

$s = new Sites();
$site = $s->get();

if ( $site->lookuptvrage == 1)
{
    $tvrage = new TVRage(true);
    $tvrage->processTvReleases(($site->lookuptvrage==1));
}

?>
