<?php

use App\Models\Category;


require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';



$page->title = 'Category List';

$categorylist = Category::getFlat();

$page->smarty->assign('categorylist', $categorylist);

$page->content = $page->smarty->fetch('category-list.tpl');
$page->render();
