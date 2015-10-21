<?php

use newznab\Releases;
use newznab\TvRage;

if (!$page->users->isLoggedIn())
	$page->show403();

if (!isset($_REQUEST["id"]))
	$page->show404();

$r = new Releases();
$rel = $r->getByGuid($_REQUEST["id"]);

if (!$rel)
	print "No tv info";
else
{

	print "<ul class=\"ui-tooltip-newznab\">\n";
	print "<li>".htmlentities($rel["tvtitle"], ENT_QUOTES)."</li>\n";
	print "<li>Aired on ".date("F j, Y", strtotime($rel["tvairdate"]))."</li>\n";
	print "</ul>";

	if (isset($rel["rageid"]) && $rel["rageid"] > 0)
	{
		$t = new TvRage();
		$rage = $t->getByRageID($rel["rageid"]);
		if (count($rage) > 0)
		{
			if ($rage[0]["hascover"] != "0")
				print "<img class=\"shadow\" src=\"".WWW_TOP."/covers/tvrage/$rage[0]['id'].jpg\" width=\"180\"/>";
		}
	}
}
