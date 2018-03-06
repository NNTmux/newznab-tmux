<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Category;
use App\Models\UserRole;
use App\Models\RoleExcludedCategory;

// Get the user roles.
$userRoles = UserRole::getRoles();
$roles = [];
foreach ($userRoles as $userRole) {
    $roles[$userRole['id']] = $userRole['name'];
}

switch (request()->input('action') ?? 'view') {
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
        if (request()->input('id') === '') {
            $role = UserRole::addRole(
                request()->input('name'),
                request()->input('apirequests'),
                request()->input('downloadrequests'),
                request()->input('defaultinvites'),
                request()->input('canpreview'),
                request()->input('hideads'),
                request()->input('donation'),
                request()->input('addyears')
            );
            header('Location:'.WWW_TOP.'/role-list.php');
        } else {
            $role = UserRole::updateRole(
                request()->input('id'),
                request()->input('name'),
                request()->input('apirequests'),
                request()->input('downloadrequests'),
                request()->input('defaultinvites'),
                request()->input('isdefault'),
                request()->input('canpreview'),
                request()->input('hideads'),
                request()->input('donation'),
                request()->input('addyears')
            );
            header('Location:'.WWW_TOP.'/role-list.php');

            request()->merge(['exccat' => (! request()->has('exccat') || ! is_array(request()->input('exccat'))) ? [] : request()->input('exccat')]);
            RoleExcludedCategory::addRoleCategoryExclusions(request()->input('id'), request()->input('exccat'));
        }
        $page->smarty->assign('role', $role);
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'User Roles Edit';
            $role = UserRole::getRoleById(request()->input('id'));
            $page->smarty->assign('role', $role);
            $page->smarty->assign('roleexccat', RoleExcludedCategory::getRoleCategoryExclusion(request()->input('id')));
        }
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('role-edit.tpl');
$page->render();
