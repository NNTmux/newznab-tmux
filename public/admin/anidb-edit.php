<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\AniDB;

$page = new AdminPage();
$AniDB = new AniDB(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
    case 'submit':
        $AniDB->updateTitle(
            $_POST['anidbid'],
                $_POST['title'],
                $_POST['type'],
                $_POST['startdate'],
                $_POST['enddate'],
                $_POST['related'],
                $_POST['similar'],
                $_POST['creators'],
                $_POST['description'],
                $_POST['rating'],
                $_POST['categories'],
                $_POST['characters'],
                $_POST['epnos'],
                $_POST['airdates'],
                $_POST['episodetitles']
        );

        if (! empty($_POST['from'])) {
            header('Location:'.$_POST['from']);
            exit;
        }

        header('Location:'.WWW_TOP.'/anidb-list.php');
        break;

    case 'view':
    default:
        if (isset($_GET['id'])) {
            $page->title = 'AniDB Edit';
            $AniDBAPIArray = $AniDB->getAnimeInfo($_GET['id']);
            $page->smarty->assign('anime', $AniDBAPIArray);
        }
        break;
}

$page->title = 'Edit AniDB Data';
$page->content = $page->smarty->fetch('anidb-edit.tpl');
$page->render();
