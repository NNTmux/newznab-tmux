<?php

use nntmux\Releases;
use nntmux\Videos;

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

	print "<ul class=\"ui-tooltip-nntmux\">\n";
	print "<li>".htmlentities($rel["title"], ENT_QUOTES)."</li>\n";
	print "<li>Aired on ".date("F j, Y", strtotime($rel["firstaired"]))."</li>\n";
	print "</ul>";

	if (isset($rel["videos_id"]) && $rel["videos_id"] > 0)
	{
		$t = new Videos();
		$show = $t->getByVideoID($rel["videos_id"]);
		if (count($show) > 0)
		{
			if ($show["image"] != "0")
				print "<img class=\"shadow\" src=\"/covers/tvshows/" . $show["id"] . ".jpg\" width=\"180\"/>";
		}
	}
}
