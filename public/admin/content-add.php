<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\User;
use Blacklight\Contents;
use Blacklight\http\AdminPage;

$page = new AdminPage();
$contents = new Contents();
$id = 0;

// Set the current action.
$action = request()->input('action') ?? 'view';

$content = [
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
        $content['showinmenu'] = '1';
        $content['status'] = '1';
        $content['contenttype'] = '2';
        break;

    case 'submit':
        // Validate and add or update.
        $returnid = 0;
        if (! request()->has('id')) {
            $returnid = $contents->add(request()->all());
        } else {
            $content = $contents->update(request()->all());
            $returnid = $content['id'];
        }
        header('Location:content-add.php?id='.$returnid);
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'Content Edit';
            $id = request()->input('id');

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
