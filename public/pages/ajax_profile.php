<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (request()->has('action') && request()->has('emailto') && (int) request()->input('action') === 1) {
    $emailto = request()->input('emalto');
    $ret = User::sendInvite($page->serverurl, User::currentUserId(), $emailto);
    if (! $ret) {
        echo 'Invite not sent.';
    } else {
        echo 'Invite sent. Alternatively paste them following link to register - '.$ret;
    }
} else {
    echo 'Invite not sent.';
}
