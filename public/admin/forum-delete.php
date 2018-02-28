<?php

use App\Models\Forumpost;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

if ($page->request->has('id')) {
    Forumpost::deletePost($page->request->input('id'));
}

if ($page->request->has('from')) {
    $referrer = $page->request->input('from');
} else {
    $referrer = $page->request->server('HTTP_REFERER');
}
header('Location: '.$referrer);
