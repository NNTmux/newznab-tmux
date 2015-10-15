<?php

require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Category;

$page = new AdminPage();

$category = new Category();

$page->title = "Category List";

$categorylist = $category->getFlat();
$page->smarty->assign('categorylist',$categorylist);

$page->content = $page->smarty->fetch('category-list.tpl');
$page->render();

