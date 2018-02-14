<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;

$page = new AdminPage();
$releases = new Releases(['Settings' => $page->pdo]);
$id = 0;

// Set the current action.
$action = ($_REQUEST['action'] ?? 'view');

switch ($action) {
    case 'submit':
        Release::updateRelease(
            $_POST['id'],
            $_POST['name'],
            $_POST['searchname'],
            $_POST['fromname'],
            $_POST['category'],
            $_POST['totalpart'],
            $_POST['grabs'],
            $_POST['size'],
            $_POST['postdate'],
            $_POST['adddate'],
            $_POST['videos_id'],
            $_POST['tv_episodes_id'],
            $_POST['imdbid'],
            $_POST['anidbid']
        );

        $release = Release::getByGuid($_POST['guid']);
        $page->smarty->assign('release', $release);

        header('Location:'.WWW_TOP.'/../details/'.$release['guid']);
        break;

    case 'view':
    default:
        $page->title = 'Release Edit';
        $id = $_GET['id'];
        $release = Release::getByGuid($id);
        $page->smarty->assign('release', $release);
        break;
}

$page->smarty->assign('yesno_ids', [1, 0]);
$page->smarty->assign('yesno_names', ['Yes', 'No']);
$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('release-edit.tpl');
$page->render();
