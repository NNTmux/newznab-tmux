<?php

use App\Models\Menu;
use Blacklight\http\AdminPage;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

$page->title = 'Menu List';

$menulist = Menu::getAll();
$page->smarty->assign('menulist', $menulist);

$page->content = $page->smarty->fetch('menu-list.tpl');
$page->render();
