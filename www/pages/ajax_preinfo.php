<?php

use newznab\controllers\PreDB;

if(!$page->users->isLoggedIn() || $page->userdata["canpre"] != 1)
	$page->show403();

if(!isset($_REQUEST['searchname']))
	$page->show404();

$PreDB = new PreDB(true);
$preRow = $PreDB->getByDirname($_REQUEST['searchname']);

print "<table class=\"ui-tooltip-tmux\">\n";
print "<tr><th>Date:</th><td>".htmlentities(date('Y-m-d H:i:s', $preRow['ctime']), ENT_QUOTES)."</td></tr>\n";
print "<tr><th>Category:</th><td>".htmlentities($preRow['category'], ENT_QUOTES)."</td></tr>\n";
if(!empty($preRow['filesize']))
	print "<tr><th>Filesize:</th><td>".htmlentities($preRow['filesize'].'MB', ENT_QUOTES)."</td></tr>\n";
if(!empty($preRow['filecount']))
	print "<tr><th>Filecount:</th><td>".htmlentities($preRow['filecount'].'F', ENT_QUOTES)."</td></tr>\n";
if(!empty($preRow['filename']))
	print "<tr><th>Filename:</th><td>".htmlentities($preRow['filename'], ENT_QUOTES)."</td></tr>\n";
if($preRow['nuketype'] && !empty($preRow['nukereason']))
	print "\n<tr><th>".$preRow['nuketype'].":</th><td>".htmlentities($preRow['nukereason'], ENT_QUOTES)."</td></tr>\n";
print "</table>";


