<?php

use App\Models\User;
use Blacklight\NZBGet;
use Blacklight\SABnzbd;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if (empty(request()->input('id'))) {
    $page->show404();
}

$user = User::find(User::currentUserId());
if ((int) $user['queuetype'] !== 2) {
    $sab = new SABnzbd($page);
    if (empty($sab->url)) {
        $page->show404();
    }
    if (empty($sab->apikey)) {
        $page->show404();
    }
    $sab->sendToSab(request()->input('id'));
} elseif ((int) $user['queuetype'] === 2) {
    $nzbget = new NZBGet($page);
    $nzbget->sendURLToNZBGet(request()->input('id'));
}
