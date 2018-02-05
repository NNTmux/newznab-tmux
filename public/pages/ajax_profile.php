<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (isset($_GET['action'], $_GET['emailto']) && (int) $_GET['action'] === 1) {
    $emailto = $_GET['emailto'];
    $ret = User::sendInvite($page->serverurl, User::currentUserId(), $emailto);
    if (! $ret) {
        echo 'Invite not sent.';
    } else {
        echo 'Invite sent. Alternatively paste them following link to register - '.$ret;
    }
} else {
    echo 'Invite not sent.';
}
