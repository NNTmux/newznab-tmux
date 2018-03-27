<?php

use App\Models\User;
use App\Models\Category;
use Blacklight\http\RSS;
use App\Models\UserRequest;
use Blacklight\utility\Utility;
use Illuminate\Support\Facades\Auth;


$rss = new RSS(['Settings' => $page->settings]);
$offset = 0;

// If no content id provided then show user the rss selection page.
if (! request()->has('t') && ! request()->has('show') && ! request()->has('anidb')) {
    // User has to either be logged in, or using rsskey.

    $page->title = 'Rss Info';
    $page->meta_title = 'Rss Nzb Info';
    $page->meta_keywords = 'view,nzb,description,details,rss,atom';
    $page->meta_description = 'View information about Newznab Tmux RSS Feeds.';

    $firstShow = $rss->getFirstInstance('videos_id', 'releases', 'id');
    $firstAni = $rss->getFirstInstance('anidbid', 'releases', 'id');

    if (isset($firstShow['videos_id'])) {
        $page->smarty->assign('show', $firstShow['videos_id']);
    } else {
        $page->smarty->assign('show', 1);
    }

    if (isset($firstAni['anidbid'])) {
        $page->smarty->assign('anidb', $firstAni['anidbid']);
    } else {
        $page->smarty->assign('anidb', 1);
    }

    $page->smarty->assign(
        [
            'categorylist'       => Category::getCategories(true, $page->userdata['categoryexclusions']),
            'parentcategorylist' => Category::getForMenu($page->userdata['categoryexclusions']),
        ]
    );

    $page->content = $page->smarty->fetch('rssdesc.tpl');
    $page->render();
} else {
    $rssToken = $uid = -1;
    // User requested a feed, ensure either logged in or passing a valid token.
    if (Auth::check()) {
        $uid = Auth::id();
        $rssToken = $page->userdata['rsstoken'];
        $maxRequests = $page->userdata->role->apirequests;
    } else {
        if (! request()->has('i') || ! request()->has('r')) {
            Utility::showApiError(100, 'Both the User ID and API key are required for viewing the RSS!');
        }

        $res = User::getByIdAndRssToken(request()->input('i'), request()->input('r'));

        if (! $res) {
            Utility::showApiError(100);
        }

        $uid = $res['id'];
        $rssToken = $res['rsstoken'];
        $maxRequests = $res->role->apirequests;
        $username = $res['username'];

        if (User::isDisabled($username)) {
            Utility::showApiError(101);
        }
    }

    if (UserRequest::getApiRequests($uid) > $maxRequests) {
        Utility::showApiError(500, 'You have reached your daily limit for API requests!');
    } else {
        UserRequest::addApiRequest($uid, request()->getRequestUri());
    }

    // Valid or logged in user, get them the requested feed.
    $userShow = $userAnidb = -1;
    if (request()->has('show')) {
        $userShow = ((int) request()->input('show') === 0 ? -1 : request()->input('show') + 0);
    } elseif (request()->has('anidb')) {
        $userAnidb = ((int) request()->input('anidb') === 0 ? -1 : request()->input('snidb') + 0);
    }

    $outputXML = (! (request()->has('o') && request()->input('o') === 'json'));

    $userCat = (request()->has('t') ? ((int) request()->input('t') === 0 ? -1 : request()->input('t')) : -1);
    $userNum = (request()->has('num') && is_numeric(request()->input('num')) ? abs(request()->input('num')) : 100);
    $userAirDate = request()->has('airdate') && is_numeric(request()->input('airdate')) ? abs(request()->input('airdate')) : -1;

    $params =
        [
            'dl'       => request()->has('dl') && request()->input('dl') === '1' ? '1' : '0',
            'del'      => request()->has('del') && request()->input('del') === '1' ? '1' : '0',
            'extended' => 1,
            'uid'      => $uid,
            'token'    => $rssToken,
        ];

    if ((int) $userCat === -3) {
        $relData = $rss->getShowsRss($userNum, $uid, User::getCategoryExclusion($uid), $userAirDate);
    } elseif ((int) $userCat === -4) {
        $relData = $rss->getMyMoviesRss($userNum, $uid, User::getCategoryExclusion($uid));
    } else {
        $relData = $rss->getRss(explode(',', $userCat), $userNum, $userShow, $userAnidb, $uid, $userAirDate);
    }
    $rss->output($relData, $params, $outputXML, $offset, 'rss');
}
