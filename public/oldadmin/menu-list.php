<?php

use App\Models\Menu;
use Blacklight\http\BasePage;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

$page = new BasePage();
$page->setAdminPrefs();

$page->title = 'Menu List';

$menulist = Menu::getAll();
$page->smarty->assign('menulist', $menulist);

$page->content = $page->smarty->fetch('menu-list.tpl');
$page->adminrender();
