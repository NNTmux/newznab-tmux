<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\MultigroupPoster;
use Blacklight\processing\ProcessReleasesMultiGroup;

$page = new AdminPage();
$relPosters = new ProcessReleasesMultiGroup(['Settings' => $page->pdo]);

// Set the current action.
$action = $page->request->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        if ($page->request->input('id') === '') {
            // Add a new mg poster.
            $poster = MultigroupPoster::query()->create(['poster' => $page->request->input('poster')]);
        } else {
            // Update an existing mg poster.
            $poster = MultigroupPoster::query()->where('id', '=', $page->request->input('id'))->update(['poster' => $page->request->input('poster')]);
        }

        header('Location:'.WWW_TOP.'/posters-list.php');
        break;

    case 'view':
    default:
        if (! empty($page->request->input('id'))) {
            $page->title = 'MultiGroup Poster Edit';
            $poster = MultigroupPoster::query()->where('id', '=', $page->request->input('id'))->firstOrFail();
        } else {
            $page->title = 'MultiGroup Poster Add';
            $poster = '';
        }
        $page->smarty->assign('poster', $poster);
        break;
}

$page->content = $page->smarty->fetch('posters-edit.tpl');
$page->render();
