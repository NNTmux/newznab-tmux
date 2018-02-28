<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = ($page->request->input('action') ?? 'view');

switch ($action) {
    case 'submit':
        Release::updateRelease(
            $page->request->input('id'),
            $page->request->input('name'),
            $page->request->input('searchname'),
            $page->request->input('fromname'),
            $page->request->input('category'),
            $page->request->input('totalpart'),
            $page->request->input('grabs'),
            $page->request->input('size'),
            $page->request->input('postdate'),
            $page->request->input('adddate'),
            $page->request->input('videos_id'),
            $page->request->input('tv_episodes_id'),
            $page->request->input('imdbid'),
            $page->request->input('anidbid')
        );

        $release = Release::getByGuid($page->request->input('guid'));
        $page->smarty->assign('release', $release);

        header('Location:'.WWW_TOP.'/../details/'.$release['guid']);
        break;

    case 'view':
    default:
        $page->title = 'Release Edit';
        $id = $page->request->input('id');
        $release = Release::getByGuid($id);
        $page->smarty->assign('release', $release);
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('release-edit.tpl');
$page->render();
