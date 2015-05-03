<?php

require_once("config.php");

$page = new AdminPage();

$s = $page->site;
unset($s->siteseed);
unset($s->amazonprivkey);
unset($s->tmdbkey);
unset($s->rottentomatokey);
if ($s->newznabID != "") $s->newznabID = "SET";
if ($s->spotnabsiteprvkey != "") $s->spotnabsiteprvkey = "SET";
unset($s->nzprekey);
unset($s->recaptchaprivatekey);
unset($s->saburl);
unset($s->sabapikey);
unset($s->sabcompletedir);
unset($s->sabvdir);

$db = new newznab\db\DB;
$totalsize = 0;
$alltables = $db->query("show table status");
foreach ($alltables as $tablename)
{
	$ret[] = $tablename['Name'];
	//$row = $db->queryOneRow("check table `".$tablename['Name']."`");
	//$mysql[]  = array ("name" => $row["Table"].":".$row["Msg_type"]."=".$row["Msg_text"], "indexsize" => $tablename["Index_length"], "datasize" => $tablename["Data_length"]) ;
	$mysql[]  = array ("name" => $tablename['name'], "indexsize" => $tablename["index_length"], "datasize" => $tablename["data_length"]) ;
	$totalsize = $totalsize + ($tablename["index_length"] + $tablename["data_length"]);
}

$page->title = "Site Debug";
$page->smarty->assign('mysql', $mysql);
$page->smarty->assign('mysqltotalsize', $totalsize);
$page->smarty->assign('site', $s);
$page->content = $page->smarty->fetch('site-debug.tpl');
$page->render();

