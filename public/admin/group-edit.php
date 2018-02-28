<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();
$id = 0;

// Set the current action.
$action = $page->request->input('action') ?? 'view';

$group = [
    'id'                    => '',
    'name'                  => '',
    'description'           => '',
    'minfilestoformrelease' => 0,
    'active'                => 0,
    'backfill'              => 0,
    'minsizetoformrelease'  => 0,
    'first_record'          => 0,
    'last_record'           => 0,
    'backfill_target'       => 0,
];

switch ($action) {
    case 'submit':
        if ($page->request->input('id') === '') {
            // Add a new group.
            $page->request->merge(['name' => Group::isValidGroup($page->request->input('name'))]);
            if ($page->request->input('name') !== false) {
                Group::addGroup($page->request->all());
            }
        } else {
            // Update an existing group.
            Group::updateGroup($page->request->all());
        }
        header('Location:'.WWW_TOP.'/group-list.php');
        break;

    case 'view':
    default:
        if ($page->request->has('id')) {
            $page->title = 'Newsgroup Edit';
            $id = $page->request->input('id');
            $group = Group::getGroupByID($id);
        } else {
            $page->title = 'Newsgroup Add';
        }
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);

$page->smarty->assign('group', $group);

$page->content = $page->smarty->fetch('group-edit.tpl');
$page->render();
