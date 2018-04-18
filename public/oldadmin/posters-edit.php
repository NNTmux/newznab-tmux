<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\http\BasePage;
use App\Models\MultigroupPoster;
use Blacklight\processing\ProcessReleasesMultiGroup;

$page = new BasePage();
$page->setAdminPrefs();
$relPosters = new ProcessReleasesMultiGroup(['Settings' => $page->pdo]);

// Set the current action.
$action = request()->input('action') ?? 'view';

switch ($action) {
    case 'submit':
        if (request()->input('id') === '') {
            // Add a new mg poster.
            $poster = MultigroupPoster::query()->create(['poster' => request()->input('poster')]);
        } else {
            // Update an existing mg poster.
            $poster = MultigroupPoster::query()->where('id', '=', request()->input('id'))->update(['poster' => request()->input('poster')]);
        }

        header('Location:'.WWW_TOP.'/posters-list.php');
        break;

    case 'view':
    default:
        if (! empty(request()->input('id'))) {
            $page->title = 'MultiGroup Poster Edit';
            $poster = MultigroupPoster::query()->where('id', '=', request()->input('id'))->firstOrFail();
        } else {
            $page->title = 'MultiGroup Poster Add';
            $poster = '';
        }
        $page->smarty->assign('poster', $poster);
        break;
}

$page->content = $page->smarty->fetch('posters-edit.tpl');
$page->adminrender();
