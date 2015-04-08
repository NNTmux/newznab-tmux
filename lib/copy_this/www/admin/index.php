<?php

require_once("config.php");
require_once(WWW_DIR."/lib/adminpage.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/framework/db.php");

$page = new AdminPage();

$page->title = "Admin Hangout";

$statusmsgs = array();

//
// status messages
//


//
// mysql config settings
//
$db = new DB();

$result = $db->queryDirect("SELECT @@group_concat_max_len, @@max_allowed_packet");
$data = $db->getAssocArray($result);
if ($data['@@group_concat_max_len'] < 8192)
	$statusmsgs[] = addmsg("MySql my.cnf setting group_concat_max_len is too low, should be >= 8192.", "http://dev.mysql.com/doc/refman/5.1/en/server-system-variables.html#sysvar_group_concat_max_len");

if ($data['@@max_allowed_packet'] < 12582912)
	$statusmsgs[] = addmsg("MySql my.cnf setting max_allowed_packet is too low, should be >= 12582912.", "http://dev.mysql.com/doc/refman/5.1/en/server-system-variables.html#sysvar_max_allowed_packet");

//
// default keys not changed
//
if ($page->site->amazonpubkey == "AKIAIPDNG5EU7LB4AD3Q" && ($page->site->lookupmusic + $page->site->lookupgames + $page->site->lookupbooks != 0))
	$statusmsgs[] = addmsg("Amazon shared key in use. Not using your own Amazon API key will result in failed amazon lookups.", "http://newznab.readthedocs.org/en/latest/faq/");
if ($page->site->rawretentiondays > 10)
	$statusmsgs[] = addmsg("Binary header retention is set at ".$page->site->rawretentiondays.". Having this value any higher than 2 can cause the database to grow very large.", "site-edit.php");

//
// database patches uptodate
//
$s = new Sites();
if (!property_exists($page->site, "dbversion"))
{
	$db = new DB();
	$db->queryInsert('INSERT INTO site (setting, value, updateddate ) VALUES (\'dbversion\', \'$Rev: 2248 $\', now())');
	$page->site = $s->get();
}

if (!preg_match("/\d+/", $page->site->dbversion, $matches))
	$statusmsgs[] = addmsg("Bad database version. ".$page->site->dbversion." cannot be parsed.", "http://newznab.readthedocs.org/en/latest/install/#updating", "exclamation");

$patches = $s->getUnappliedPatches($page->site);
$patches = array_map("basename", $patches);

if (count($patches) > 0)
	$statusmsgs[] = addmsg("Database out of date. Ensure all database patches in /db/patch/0.2.3/ are ran by using the script misc/update_scripts/update_database_version.php<br/><small>".implode("<br/>", $patches)."</small>", "http://newznab.readthedocs.org/en/latest/install/#updating", "exclamation");


$page->smarty->assign('statusmsgs', $statusmsgs);
$page->content = $page->smarty->fetch('index.tpl');
$page->render();

function addmsg($msg, $url = "", $icon = "")
{
	return array( 'msg'=> $msg,	'url' => $url, 'icon' => ($icon==""?"information":$icon));
}