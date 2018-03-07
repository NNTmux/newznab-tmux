<?php

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->settings]);

// Set the current action.
$action = request()->input('action') ?? '';

// Request is for id, but guid is actually being provided
if (request()->has('id') && is_array(request()->input('id'))) {
    $id = request()->input('id');
    //Get info for first guid to populate form
    $rel = Release::getByGuid(request()->input('id')[0]);
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
                request()->input('id'),
                request()->input('category'),
                request()->input('grabs'),
                request()->input('videosid'),
                request()->input('episodesid'),
                request()->input('anidbid'),
                request()->input('imdbid')
            );
        }
        $page->smarty->assign('release', $rel);
        $page->smarty->assign('success', $success);
        $page->smarty->assign('from', request()->input('from') ?? '');
        $page->smarty->assign('catlist', Category::getForSelect(false));
        $page->content = $page->smarty->fetch('ajax_release-edit.tpl');
        echo $page->content;

        break;
    case 'dodelete':
        $releases->deleteMultiple(request()->input('id'));
        break;
    default:
        $page->show404();
        break;
}
