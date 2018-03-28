<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\UserRole;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();

$page->title = 'User Role List';

//get the user roles
$userroles = UserRole::getRoles();

$page->smarty->assign('userroles', $userroles);

$page->content = $page->smarty->fetch('role-list.tpl');
$page->adminrender();
