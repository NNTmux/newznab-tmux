<?php

require_once("config.php");

use newznab\ReleaseRegex;

$page = new AdminPage();

$reg = new ReleaseRegex();

$page->title = "Release Regex List";

$reggrouplist = $reg->getGroupsForSelect();
$page->smarty->assign('reggrouplist', $reggrouplist);

$group=".*";
if (isset($_REQUEST["group"]))
	$group = $_REQUEST["group"];

$page->smarty->assign('selectedgroup', $group);

$regexlist = $reg->get(false, $group, true);
$page->smarty->assign('regexlist', $regexlist);

$page->content = $page->smarty->fetch('regex-list.tpl');
$page->render();

