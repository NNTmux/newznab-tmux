<?php

use App\Models\User;
use Blacklight\NZBGet;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (empty($page->request->input('id'))) {
    $page->show404();
}

$nzbget = new NZBGet($page);

if (empty($nzbget->url)) {
    $page->show404();
}

if (empty($nzbget->username)) {
    $page->show404();
}

if (empty($nzbget->password)) {
    $page->show404();
}

$guid = $page->request->input('id');

$nzbget->sendURLToNZBGet($guid);
