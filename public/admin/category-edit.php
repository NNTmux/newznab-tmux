<?php

use App\Models\Category;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();
$id = 0;

// set the current action
$action = $page->request->input('action') ?? 'view';

switch ($action) {
    case 'submit':
		$ret = Category::updateCategory($page->request->input('id'), $page->request->input('status'), $page->request->input('description'),
			$page->request->input('disablepreview'), $page->request->input('minsizetoformrelease'), $page->request->input('maxsizetoformrelease'));
		header('Location:'.WWW_TOP.'/category-list.php');
		break;
    case 'view':
    default:
			if ($page->request->has('id')) {
			    $page->title = 'Category Edit';
			    $id = $page->request->input('id');
			    $cat = Category::find($id);
			    $page->smarty->assign('category', $cat);
			}
		break;
}

$page->smarty->assign('status_ids', [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE]);
$page->smarty->assign('status_names', ['Yes', 'No']);

$page->content = $page->smarty->fetch('category-edit.tpl');
$page->render();
