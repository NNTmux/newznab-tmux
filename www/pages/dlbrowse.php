<?php
require_once("config.php");

use newznab\db\Settings;
use newznab\utility\Utility;

if (!$page->users->isLoggedIn())
	$page->show403();

$r = new Releases();
$pdo = new Settings();

if ($pdo->getSetting('sabvdir') == "" || $pdo->getSetting('sabcompletedir') == "" || !file_exists($pdo->getSetting('sabcompletedir')))
	$page->show404();

if (!Utility::endsWith($pdo->getSetting('sabcompletedir'), "/"))
	$pdo->getSetting('sabcompletedir');
if (!Utility::endsWith($pdo->getSetting('sabvdir'), "/"))
	$pdo->getSetting('sabvdir');


$basepath = $pdo->getSetting('sabcompletedir');
$webpath = $pdo->getSetting('sabvdir');

$subpath = "";
if (isset($_REQUEST["sp"]))
	$subpath = urldecode($_REQUEST["sp"]);

$listmode = true;
if (isset($_REQUEST["lm"]))
	$listmode = ($_REQUEST["lm"] == "1");

$path = $basepath.$subpath;
$webpath.=$subpath;

if (!Utility::startsWith(realpath($path), realpath($basepath)))
	$page->show403();

$files = glob(preg_replace('/(\*|\?|\[)/', '[$1]', $path)."/*");

$items = [];
$dirs = [];
foreach($files as $f)
{
	$i = [];
	$i["name"] = basename($f);
	$i["isdir"] = (is_dir($f)?1:0);
	$i["fullname"] = $f;
	$i["webpath"] = $webpath."/".$i["name"].($i["isdir"] == 1?"/":"");
	$i["mtime"] = filemtime($f);
	$i["pathinfo"] = pathinfo($f);
	if ($i["isdir"] == 1)
	{
		$i["pathinfo"]["extension"] = "";
		$dirs[] = $i["name"];
	}
	else
	{
		$i["size"] = filesize($f);
	}
	$items[$i["name"]] = $i;
}

if (!$listmode)
{
	$relres = $r->getByNames($dirs);
	while ($rel = $pdo->getAssocArray($relres))
	{
		if (isset($items[$rel["searchname"]]))
			$items[$rel["searchname"]]["release"] = $rel;
	}
}

uasort($items, 'sortbymodified');

$page->smarty->assign('results', $items);
$page->smarty->assign('lm', $listmode);

if ($subpath != "")
	$page->smarty->assign('subpath', $subpath."/");

$parentpath	="";
if ($subpath != "")
{
	$pos = strrpos($subpath, "/");
	if ($pos !== false)
		$parentpath = substr($subpath, 0, $pos);
	else
		$parentpath = "-1";

	$page->smarty->assign('parentpath', $parentpath);
}

$page->meta_title = "download browse";
$page->meta_keywords = "downloads,browse";
$page->meta_description = "browse downloaded files";

$page->content = $page->smarty->fetch('dlbrowse.tpl');
$page->render();


function sortbymodified($a, $b)
{
	if ($a["isdir"] == 1 && $b["isdir"] == 0)
		return -1;
	if ($a["isdir"] == 0 && $b["isdir"] == 1)
		return 1;

	if ($a["mtime"] == $b["mtime"]) {
		return 0;
	}
	return ($a["mtime"] > $b["mtime"]) ? -1 : 1;
}