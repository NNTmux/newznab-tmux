<?php

use App\Models\Category;
use Blacklight\http\AdminPage;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();
$page->title = 'Category List';

$categorylist = Category::getFlat();

$page->smarty->assign('categorylist', $categorylist);

$page->content = $page->smarty->fetch('category-list.tpl');
$page->render();
