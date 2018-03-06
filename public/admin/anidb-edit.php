<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;



$AniDB = new AniDB(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = request()->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        $AniDB->updateTitle(
            request()->input('anidbid'),
                request()->input('title'),
                request()->input('type'),
                request()->input('startdate'),
                request()->input('enddate'),
                request()->input('related'),
                request()->input('similar'),
                request()->input('creators'),
                request()->input('description'),
                request()->input('rating'),
                request()->input('categories'),
                request()->input('characters'),
                request()->input('epnos'),
                request()->input('airdates'),
            request()->input('episodetitles')
        );

        if (! empty(request()->input('from'))) {
            header('Location:'.request()->input('from'));
            exit;
        }

        header('Location:'.WWW_TOP.'/anidb-list.php');
        break;

    case 'view':
    default:
        if (request()->has('id')) {
            $page->title = 'AniDB Edit';
            $AniDBAPIArray = $AniDB->getAnimeInfo(request()->input('id'));
            $page->smarty->assign('anime', $AniDBAPIArray);
        }
        break;
}

$page->title = 'Edit AniDB Data';
$page->content = $page->smarty->fetch('anidb-edit.tpl');
$page->render();
