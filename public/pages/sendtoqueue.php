<?php

use App\Models\User;
use nntmux\NZBGet;
use nntmux\SABnzbd;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (empty($_GET['id'])) {
    $page->show404();
}

$user = User::getById(User::currentUserId());
if ($user['queuetype'] != 2) {
    $sab = new SABnzbd($page);
    if (empty($sab->url)) {
        $page->show404();
    }
    if (empty($sab->apikey)) {
        $page->show404();
    }
    $sab->sendToSab($_GET['id']);
} elseif ($user['queuetype'] == 2) {
    $nzbget = new NZBGet($page);
    $nzbget->sendURLToNZBGet($_GET['id']);
}
