<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\User;

$page = new AdminPage();

if ($page->request->has('id')) {
    User::deleteUser($page->request->input('id'));
}

if ($page->request->has('redir')) {
    header('Location: '.$page->request->input('redir'));
} else {
    $referrer = $page->request->server('HTTP_REFERER');
    header('Location: '.$referrer);
}
