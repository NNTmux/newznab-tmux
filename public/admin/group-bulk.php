<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();

// set the current action
$action = $page->request->input('action') ?? 'view';

switch ($action) {
	case 'submit':
		if ($page->request->has('groupfilter') && ! empty($page->request->input('groupfilter'))) {
		    $msgs = Group::addBulk($page->request->input('groupfilter'), $page->request->input('active'), $page->request->input('backfill'));
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
