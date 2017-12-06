<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use nntmux\Users;
use App\Models\UserRole;

$page = new AdminPage();

$users = new Users();

$page->title = 'User Role List';

//get the user roles
$userroles = UserRole::getRoles();

$page->smarty->assign('userroles', $userroles);

$page->content = $page->smarty->fetch('role-list.tpl');
$page->render();
