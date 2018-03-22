<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if (request()->has('action') && request()->has('emailto') && (int) request()->input('action') === 1) {
    $emailto = request()->input('emalto');
    $ret = User::sendInvite($page->serverurl, Auth::id(), $emailto);
    if (! $ret) {
        echo 'Invite not sent.';
    } else {
        echo 'Invite sent. Alternatively paste them following link to register - '.$ret;
    }
} else {
    echo 'Invite not sent.';
}
