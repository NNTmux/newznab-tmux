<?php

use App\Models\User;
use App\Models\Release;
use Illuminate\Support\Facades\Auth;

// Page is accessible only by the rss token, or logged in users.
if (Auth::check()) {
    $uid = Auth::id();
    $rssToken = $page->userdata['rsstoken'];
} else {
    if (! request()->has('userid') || ! request()->has('rsstoken')) {
        header('X-DNZB-RCode: 400');
        header('X-DNZB-RText: Bad request, please supply all parameters!');
        $page->show403();
    } else {
        $res = User::getByIdAndRssToken(request()->input('userid'), request()->input('rsstoken'));
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

if (isset($uid, $rssToken) && is_numeric($uid) && request()->has('guid')) {
    $alt = Release::getAlternate(request()->input('guid'), $uid);
    if ($alt === null) {
        header('X-DNZB-RCode: 404');
        header('X-DNZB-RText: No NZB found for alternate match.');
        $page->show404();
    } else {
        header('Location: '.$page->serverurl.'getnzb/'.$alt['guid'].'&i='.$uid.'&r='.$rssToken);
    }
}
