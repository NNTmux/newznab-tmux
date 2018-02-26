<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\User;
use Blacklight\Contents;

$page = new AdminPage();
$contents = new Contents();
$id = 0;

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

$content = (object) [
    'id' => '',
    'title' => '',
    'url' => '',
    'body' => '',
    'metadescription' => '',
    'metakeywords' => '',
    'contenttype' => '',
    'showinmenu' => '',
    'status' => '',
    'ordinal' => '',
    'created_at' => '',
    'role' => '',
];

switch ($action) {
    case 'add':
        $page->title = 'Content Add';
        $content->showinmenu = '1';
        $content->status = '1';
        $content->contenttype = '2';
        break;

    case 'submit':
        // Validate and add or update.
        $returnid = 0;
        if (! isset($_POST['id']) || $_POST['id'] === '') {
            $returnid = $contents->add($_POST);
        } else {
            $content = $contents->update($_POST);
            $returnid = $content->id;
        }
        header('Location:content-add.php?id='.$returnid);
        break;

    case 'view':
    default:
        if (isset($_GET['id'])) {
            $page->title = 'Content Edit';
            $id = $_GET['id'];

            $content = $contents->getByID($id, User::ROLE_ADMIN);
        }
        break;
}

$page->smarty->assign('status_ids', [1, 0]);
$page->smarty->assign('status_names', ['Enabled', 'Disabled']);

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);

$contenttypelist = ['1' => 'Useful Link', '2' => 'Article', '3' => 'Homepage'];
$page->smarty->assign('contenttypelist', $contenttypelist);

$page->smarty->assign('content', $content);

$rolelist = ['0' => 'Everyone', '1' => 'Logged in Users', '2' => 'Admins'];
$page->smarty->assign('rolelist', $rolelist);

$page->content = $page->smarty->fetch('content-add.tpl');
$page->render();
