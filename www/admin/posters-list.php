<?php
require_once './config.php';

use nntmux\db\DB;

$page   = new AdminPage();
$pdo = new DB();
$posterslist = $pdo->query(sprintf('SELECT * FROM mgr_posters'));

$posters = (isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? $_REQUEST['id'] : '');

$page->smarty->assign(
	[
		'poster' => $posters,
		'posterslist' => $posterslist
	]
);

$page->title = 'MGR Posters List';
$page->content = $page->smarty->fetch('posters-list.tpl');
$page->render();
