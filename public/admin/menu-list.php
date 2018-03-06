<?php

use App\Models\Menu;


require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';



$page->title = 'Menu List';

$menulist = Menu::getAll();
$page->smarty->assign('menulist', $menulist);

$page->content = $page->smarty->fetch('menu-list.tpl');
$page->render();
