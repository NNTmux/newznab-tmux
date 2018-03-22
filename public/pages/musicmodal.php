<?php

use Blacklight\Music;

$music = new Music;

if (request()->has('id') && ctype_digit(request()->input('id'))) {
    $mus = $music->getMusicInfo(request()->input('id'));

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
    if (request()->has('modal')) {
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
