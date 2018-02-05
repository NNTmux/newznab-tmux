<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage();
$id = 0;

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

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
        if ($_POST['id'] === '') {
            // Add a new group.
            $_POST['name'] = Group::isValidGroup($_POST['name']);
            if ($_POST['name'] !== false) {
                Group::addGroup($_POST);
            }
        } else {
            // Update an existing group.
            Group::updateGroup($_POST);
        }
        header('Location:'.WWW_TOP.'/group-list.php');
        break;

    case 'view':
    default:
        if (isset($_GET['id'])) {
            $page->title = 'Newsgroup Edit';
            $id = $_GET['id'];
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
