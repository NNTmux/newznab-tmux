<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();

// set the current action
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
	case 'submit':
		if (isset($_POST['groupfilter']) && ! empty($_POST['groupfilter'])) {
		    $msgs = Group::addBulk($_POST['groupfilter'], $_POST['active'], $_POST['backfill']);
		}
		break;
	default:
		$msgs = '';
		break;
}

$page->smarty->assign('groupmsglist', $msgs);
$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);

$page->title = 'Bulk Add Newsgroups';
$page->content = $page->smarty->fetch('group-bulk.tpl');
$page->render();
