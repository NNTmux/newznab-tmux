<?php

use App\Models\User;
use App\Models\Release;

// Page is accessible only by the rss token, or logged in users.
if (User::isLoggedIn()) {
    $uid = User::currentUserId();
    $rssToken = $page->userdata['rsstoken'];
} else {
    if (! $page->request->has('userid') || ! $page->request->has('rsstoken')) {
        header('X-DNZB-RCode: 400');
        header('X-DNZB-RText: Bad request, please supply all parameters!');
        $page->show403();
    } else {
        $res = User::getByIdAndRssToken($page->request->input('userid'), $page->request->input('rsstoken'));
    }
    if (! isset($res)) {
        header('X-DNZB-RCode: 401');
        header('X-DNZB-RText: Unauthorised, wrong user ID or rss key!');
        $page->show403();
    } else {
        $uid = $res['id'];
        $rssToken = $res['rsstoken'];
    }
}

if (isset($uid, $rssToken) && is_numeric($uid) && $page->request->has('guid')) {
    $alt = Release::getAlternate($page->request->input('guid'), $uid);
    if ($alt === null) {
        header('X-DNZB-RCode: 404');
        header('X-DNZB-RText: No NZB found for alternate match.');
        $page->show404();
    } else {
        header('Location: '.$page->serverurl.'getnzb/'.$alt['guid'].'&i='.$uid.'&r='.$rssToken);
    }
}
