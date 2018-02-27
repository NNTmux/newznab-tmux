<?php

use App\Models\User;
use Blacklight\Music;

$music = new Music;

if (! User::isLoggedIn()) {
    $page->show403();
}

if ($page->request->has('id') && ctype_digit($page->request->input('id'))) {
    $mus = $music->getMusicInfo($page->request->input('id'));

    if (! $mus) {
        $page->show404();
    }

    $page->smarty->assign('music', $mus);

    $page->title = 'Info for '.$mus['title'];
    $page->meta_title = '';
    $page->meta_keywords = '';
    $page->meta_description = '';
    $page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

    $modal = false;
    if (isset($_GET['modal'])) {
        $modal = true;
        $page->smarty->assign('modal', true);
    }

    $page->content = $page->smarty->fetch('viewmusic.tpl');

    if ($modal) {
        echo $page->content;
    } else {
        $page->render();
    }
}
