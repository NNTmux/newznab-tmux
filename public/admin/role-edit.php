<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use nntmux\Category;
use App\Models\UserRole;

$category = new Category();
$page = new AdminPage();

// Get the user roles.
$userRoles = UserRole::getRoles();
$roles = [];
foreach ($userRoles as $userRole) {
    $roles[$userRole['id']] = $userRole['name'];
}

switch ($_REQUEST['action'] ?? 'view') {
    case 'add':
        $page->title = 'User Roles Add';
        $role = [
            'id'               => '',
            'name'             => '',
            'apirequests'      => '',
            'downloadrequests' => '',
            'defaultinvites'   => '',
            'canpreview'       => 0,
            'hideads'          => 0,
            'donation'         => 0,
            'addyears'         => 0,
        ];
        $page->smarty->assign('role', $role);
        break;

    case 'submit':
        if ($_POST['id'] === '') {
            $role = UserRole::addRole(
                $_POST['name'],
                $_POST['apirequests'],
                $_POST['downloadrequests'],
                $_POST['defaultinvites'],
                $_POST['canpreview'],
                $_POST['hideads'],
                $_POST['donation'],
                $_POST['addyears']
            );
            header('Location:'.WWW_TOP.'/role-list.php');
        } else {
            $role = UserRole::updateRole(
                $_POST['id'],
                $_POST['name'],
                $_POST['apirequests'],
                $_POST['downloadrequests'],
                $_POST['defaultinvites'],
                $_POST['isdefault'],
                $_POST['canpreview'],
                $_POST['hideads'],
                $_POST['donation'],
                $_POST['addyears']
            );
            header('Location:'.WWW_TOP.'/role-list.php');

            $_POST['exccat'] = (! isset($_POST['exccat']) || ! is_array($_POST['exccat'])) ? [] : $_POST['exccat'];
            $page->users->addRoleCategoryExclusions($_POST['id'], $_POST['exccat']);
        }
        $page->smarty->assign('role', $role);
        break;

    case 'view':
    default:
        if (isset($_GET['id'])) {
            $page->title = 'User Roles Edit';
            $role = UserRole::getRoleById($_GET['id']);
            $page->smarty->assign('role', $role);
            $page->smarty->assign('roleexccat', $page->users->getRoleCategoryExclusion($_GET['id']));
        }
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', $category->getForSelect(false));

$page->content = $page->smarty->fetch('role-edit.tpl');
$page->render();
