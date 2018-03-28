<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\Group;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

// set the current action
$action = request()->input('action') ?? 'view';

switch ($action) {
	case 'submit':
		if (request()->has('groupfilter') && ! empty(request()->input('groupfilter'))) {
		    $msgs = Group::addBulk(request()->input('groupfilter'), request()->input('active'), request()->input('backfill'));
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
$page->adminrender();
