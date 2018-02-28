<?php

use App\Models\ReleaseComment;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

if ($page->request->has('id')) {
    ReleaseComment::deleteComment($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');
header('Location: '.$referrer);
