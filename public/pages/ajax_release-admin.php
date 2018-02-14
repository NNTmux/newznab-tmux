<?php

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->settings]);

// Set the current action.
$action = $_REQUEST['action'] ?? '';

// Request is for id, but guid is actually being provided
if (isset($_REQUEST['id']) && is_array($_REQUEST['id'])) {
    $id = $_REQUEST['id'];
    //Get info for first guid to populate form
    $rel = Release::getByGuid($_REQUEST['id'][0]);
} else {
    $id = $rel = '';
}

$page->smarty->assign('action', $action);
$page->smarty->assign('idArr', $id);

switch ($action) {
    case 'doedit':
    case 'edit':
        $success = false;
        if ($action === 'doedit') {
            $success = $releases->updateMulti(
                $_POST['id'],
                $_POST['category'],
                $_POST['grabs'],
                $_POST['videosid'],
                $_POST['episodesid'],
                $_POST['anidbid'],
                $_POST['imdbid']
            );
        }
        $page->smarty->assign('release', $rel);
        $page->smarty->assign('success', $success);
        $page->smarty->assign('from', $_POST['from'] ?? '');
        $page->smarty->assign('catlist', Category::getForSelect(false));
        $page->content = $page->smarty->fetch('ajax_release-edit.tpl');
        echo $page->content;

        break;
    case 'dodelete':
        $releases->deleteMultiple($_GET['id']);
        break;
    default:
        $page->show404();
        break;
}
