<?php

require_once './config.php';

use newznab\db\Settings;

$page = new AdminPage();

$page->title = "Admin Hangout";

$statusmsgs = [];

//
// status messages
//


//
// mysql config settings
//
$db = new Settings();

$result = $db->queryDirect("SELECT @@group_concat_max_len, @@max_allowed_packet");
$data = $db->getAssocArray($result);
if ($data['@@group_concat_max_len'] < 8192)
	$statusmsgs[] = addmsg("MySql my.cnf setting group_concat_max_len is too low, should be >= 8192.", "http://dev.mysql.com/doc/refman/5.1/en/server-system-variables.html#sysvar_group_concat_max_len");

if ($data['@@max_allowed_packet'] < 12582912)
	$statusmsgs[] = addmsg("MySql my.cnf setting max_allowed_packet is too low, should be >= 12582912.", "http://dev.mysql.com/doc/refman/5.1/en/server-system-variables.html#sysvar_max_allowed_packet");

//
// default keys not changed
//
if ($page->settings->getSetting('amazonpubkey') == "AKIAIPDNG5EU7LB4AD3Q" && ($page->settings->getSetting('lookupmusic') + $page->settings->getSetting('lookupgames') + $page->settings->getSetting('lookupbooks') != 0))
	$statusmsgs[] = addmsg("Amazon shared key in use. Not using your own Amazon API key will result in failed amazon lookups.", "http://newznab.readthedocs.org/en/latest/faq/");
if ($page->settings->getSetting('rawretentiondays') > 10)
	$statusmsgs[] = addmsg("Binary header retention is set at ".$page->settings->getSetting('rawretentiondays').". Having this value any higher than 2 can cause the database to grow very large.", "site-edit.php");

$page->smarty->assign('statusmsgs', $statusmsgs);
$page->content = $page->smarty->fetch('index.tpl');
$page->render();

function addmsg($msg, $url = "", $icon = "")
{
	return array( 'msg'=> $msg,	'url' => $url, 'icon' => ($icon==""?"information":$icon));
}