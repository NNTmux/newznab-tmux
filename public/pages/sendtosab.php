<?php

use App\Models\User;
use Blacklight\SABnzbd;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (empty(\request()->input('id'))) {
    $page->show404();
}

$sab = new SABnzbd($page);

if (empty($sab->url)) {
    $page->show404();
}

if (empty($sab->apikey)) {
    $page->show404();
}

$guid = \request()->input('id');

$sab->sendToSab($guid);
