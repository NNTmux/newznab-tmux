<?php

use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}

if ($page->request->has('action') && $page->request->has('emailto') && (int) $page->request->input('action') === 1) {
    $emailto = $page->request->input('emalto');
    $ret = User::sendInvite($page->serverurl, User::currentUserId(), $emailto);
    if (! $ret) {
        echo 'Invite not sent.';
    } else {
        echo 'Invite sent. Alternatively paste them following link to register - '.$ret;
    }
} else {
    echo 'Invite not sent.';
}
