<?php

require_once './config.php';

use newznab\db\Settings;
$page = new AdminPage();

$db = new Settings;
$tablelist = $db->optimise();

$page->title = "DB Table Optimise";
$page->smarty->assign('tablelist',$tablelist);
$page->content = $page->smarty->fetch('db-optimise.tpl');
$page->render();
