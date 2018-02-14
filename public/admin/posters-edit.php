<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\MultigroupPoster;
use Blacklight\processing\ProcessReleasesMultiGroup;

$page = new AdminPage();
$relPosters = new ProcessReleasesMultiGroup(['Settings' => $page->pdo]);

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
    case 'submit':
        if ($_POST['id'] === '') {
            // Add a new mg poster.
            $poster = MultigroupPoster::query()->create(['poster' => $_POST['poster']]);
        } else {
            // Update an existing mg poster.
            $poster = MultigroupPoster::query()->where('id', '=', $_POST['id'])->update(['poster' => $_POST['poster']]);
        }

        header('Location:'.WWW_TOP.'/posters-list.php');
        break;

    case 'view':
    default:
        if (! empty($_GET['id'])) {
            $page->title = 'MultiGroup Poster Edit';
            $poster = MultigroupPoster::query()->where('id', '=', $_GET['id'])->firstOrFail();
        } else {
            $page->title = 'MultiGroup Poster Add';
            $poster = '';
        }
        $page->smarty->assign('poster', $poster);
        break;
}

$page->content = $page->smarty->fetch('posters-edit.tpl');
$page->render();
