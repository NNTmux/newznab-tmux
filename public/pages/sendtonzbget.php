<?php

use Blacklight\NZBGet;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if (empty(request()->input('id'))) {
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

$guid = request()->input('id');

$nzbget->sendURLToNZBGet($guid);
