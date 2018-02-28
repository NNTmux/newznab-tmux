<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;

$page = new AdminPage();
$AniDB = new AniDB(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = $page->request->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        $AniDB->updateTitle(
            $page->request->input('anidbid'),
                $page->request->input('title'),
                $page->request->input('type'),
                $page->request->input('startdate'),
                $page->request->input('enddate'),
                $page->request->input('related'),
                $page->request->input('similar'),
                $page->request->input('creators'),
                $page->request->input('description'),
                $page->request->input('rating'),
                $page->request->input('categories'),
                $page->request->input('characters'),
                $page->request->input('epnos'),
                $page->request->input('airdates'),
            $page->request->input('episodetitles')
        );

        if (! empty($page->request->input('from'))) {
            header('Location:'.$page->request->input('from'));
            exit;
        }

        header('Location:'.WWW_TOP.'/anidb-list.php');
        break;

    case 'view':
    default:
        if ($page->request->has('id')) {
            $page->title = 'AniDB Edit';
            $AniDBAPIArray = $AniDB->getAnimeInfo($page->request->input('id'));
            $page->smarty->assign('anime', $AniDBAPIArray);
        }
        break;
}

$page->title = 'Edit AniDB Data';
$page->content = $page->smarty->fetch('anidb-edit.tpl');
$page->render();
