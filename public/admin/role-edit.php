<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Category;
use App\Models\UserRole;
use App\Models\RoleExcludedCategory;

$page = new AdminPage();

// Get the user roles.
$userRoles = UserRole::getRoles();
$roles = [];
foreach ($userRoles as $userRole) {
    $roles[$userRole['id']] = $userRole['name'];
}

switch ($page->request->input('action') ?? 'view') {
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
        if ($page->request->input('id') === '') {
            $role = UserRole::addRole(
                $page->request->input('name'),
                $page->request->input('apirequests'),
                $page->request->input('downloadrequests'),
                $page->request->input('defaultinvites'),
                $page->request->input('canpreview'),
                $page->request->input('hideads'),
                $page->request->input('donation'),
                $page->request->input('addyears')
            );
            header('Location:'.WWW_TOP.'/role-list.php');
        } else {
            $role = UserRole::updateRole(
                $page->request->input('id'),
                $page->request->input('name'),
                $page->request->input('apirequests'),
                $page->request->input('downloadrequests'),
                $page->request->input('defaultinvites'),
                $page->request->input('isdefault'),
                $page->request->input('canpreview'),
                $page->request->input('hideads'),
                $page->request->input('donation'),
                $page->request->input('addyears')
            );
            header('Location:'.WWW_TOP.'/role-list.php');

            $page->request->merge(['exccat' => (! $page->request->has('exccat') || ! is_array($page->request->input('exccat'))) ? [] : $page->request->input('exccat')]);
            RoleExcludedCategory::addRoleCategoryExclusions($page->request->input('id'), $page->request->input('exccat'));
        }
        $page->smarty->assign('role', $role);
        break;

    case 'view':
    default:
        if ($page->request->has('id')) {
            $page->title = 'User Roles Edit';
            $role = UserRole::getRoleById($page->request->input('id'));
            $page->smarty->assign('role', $role);
            $page->smarty->assign('roleexccat', RoleExcludedCategory::getRoleCategoryExclusion($page->request->input('id')));
        }
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('role-edit.tpl');
$page->render();
