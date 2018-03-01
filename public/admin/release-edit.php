<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = (\request()->input('action') ?? 'view');

switch ($action) {
    case 'submit':
        Release::updateRelease(
            \request()->input('id'),
            \request()->input('name'),
            \request()->input('searchname'),
            \request()->input('fromname'),
            \request()->input('category'),
            \request()->input('totalpart'),
            \request()->input('grabs'),
            \request()->input('size'),
            \request()->input('postdate'),
            \request()->input('adddate'),
            \request()->input('videos_id'),
            \request()->input('tv_episodes_id'),
            \request()->input('imdbid'),
            \request()->input('anidbid')
        );

        $release = Release::getByGuid(\request()->input('guid'));
        $page->smarty->assign('release', $release);

        header('Location:'.WWW_TOP.'/../details/'.$release['guid']);
        break;

    case 'view':
    default:
        $page->title = 'Release Edit';
        $id = \request()->input('id');
        $release = Release::getByGuid($id);
        $page->smarty->assign('release', $release);
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('release-edit.tpl');
$page->render();
