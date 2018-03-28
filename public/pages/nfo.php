<?php

use App\Models\Release;
use App\Models\ReleaseNfo;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if (request()->has('id')) {
    $rel = Release::getByGuid(request()->input('id'));

    if (! $rel) {
        $page->show404();
    }

    $nfo = ReleaseNfo::getReleaseNfo($rel['id']);
    $nfo['nfoUTF'] = Utility::cp437toUTF($nfo['nfo']);

    $page->smarty->assign('rel', $rel);
    $page->smarty->assign('nfo', $nfo);

    $page->title = 'NFO File';
    $page->meta_title = 'View Nfo';
    $page->meta_keywords = 'view,nzb,nfo,description,details';
    $page->meta_description = 'View Nfo File';

    $modal = false;
    if (request()->has('modal')) {
        $modal = true;
        $page->smarty->assign('modal', true);
    }

    $page->content = $page->smarty->fetch('viewnfo.tpl');

    if ($modal) {
        echo $page->content;
    } else {
        $page->pagerender();
    }
}
