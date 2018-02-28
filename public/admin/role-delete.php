<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\UserRole;

$page = new AdminPage();

if ($page->request->has('id')) {
    UserRole::deleteRole($page->request->input('id'));
}

$referrer = $page->request->server('HTTP_REFERER');
header('Location: '.$referrer);
