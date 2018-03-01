<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Menu;
use App\Models\UserRole;

$page = new AdminPage();
$id = 0;

// Get the user roles.
$userroles = UserRole::getRoles();
$roles = [];
foreach ($userroles as $r) {
    $roles[$r['id']] = $r['name'];
}

// set the current action
$action = \request()->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        if (\request()->input('id') === '') {
            \App\Models\Menu::addMenu(\request()->all());
        } else {
            $ret = Menu::updateMenu(\request()->all());
        }

        header('Location:'.WWW_TOP.'/menu-list.php');
        break;

    case 'view':
    default:
        $menuRow = [
            'id' => '', 'title' => '', 'href' => '', 'tooltip' => '',
            'menueval' => '', 'role' => 0, 'ordinal' => 0, 'newwindow' => 0,
        ];
        if (\request()->has('id')) {
            $id = \request()->input('id');
            $menuRow = Menu::find($id);
        }
        $page->title = 'Menu Edit';
        $page->smarty->assign('menu', $menuRow);
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);

$page->smarty->assign('role_ids', array_keys($roles));
$page->smarty->assign('role_names', $roles);

$page->content = $page->smarty->fetch('menu-edit.tpl');
$page->render();
