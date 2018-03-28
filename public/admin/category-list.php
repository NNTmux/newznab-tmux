<?php

use App\Models\Category;
use Blacklight\http\BasePage;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

$page = new BasePage();
$page->setAdminPrefs();
$page->title = 'Category List';

$categorylist = Category::getFlat();

$page->smarty->assign('categorylist', $categorylist);

$page->content = $page->smarty->fetch('category-list.tpl');
$page->adminrender();
