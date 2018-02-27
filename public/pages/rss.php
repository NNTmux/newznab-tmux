<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\http\RSS;
use App\Models\UserRequest;
use Blacklight\utility\Utility;

$rss = new RSS(['Settings' => $page->settings]);
$offset = 0;

// If no content id provided then show user the rss selection page.
if (! $page->request->has('t') && ! isset($_GET['show']) && ! isset($_GET['anidb'])) {
    // User has to either be logged in, or using rsskey.
    if (! User::isLoggedIn()) {
        header('Location: '.Settings::settingValue('site.main.code'));
    }

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
    if (User::isLoggedIn()) {
        $uid = $page->userdata['id'];
        $rssToken = $page->userdata['rsstoken'];
        $maxRequests = $page->userdata->role->apirequests;
    } else {
        if (! $page->request->has('i') || ! isset($page->request->input('r'))) {
            Utility::showApiError(100, 'Both the User ID and API key are required for viewing the RSS!');
        }

        $res = User::getByIdAndRssToken($page->request->input('i'), $page->request->input('r'));

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
        UserRequest::addApiRequest($uid, $page->request->getRequestUri());
    }

    // Valid or logged in user, get them the requested feed.
    $userShow = $userAnidb = -1;
    if (isset($_GET['show'])) {
        $userShow = ((int) $_GET['show'] === 0 ? -1 : $_GET['show'] + 0);
    } elseif (isset($_GET['anidb'])) {
        $userAnidb = ((int) $_GET['anidb'] === 0 ? -1 : $_GET['anidb'] + 0);
    }

    $outputXML = (! (isset($_GET['o']) && $_GET['o'] === 'json'));

    $userCat = ($page->request->has('t') ? ((int) $_GET['t'] === 0 ? -1 : $_GET['t']) : -1);
    $userNum = (isset($_GET['num']) && is_numeric($_GET['num']) ? abs($_GET['num']) : 100);
    $userAirDate = (isset($_GET['airdate']) && is_numeric($_GET['airdate']) ? abs($_GET['airdate']) : -1);

    $params =
        [
            'dl'       => isset($_GET['dl']) && $_GET['dl'] === '1' ? '1' : '0',
            'del'      => isset($_GET['del']) && $_GET['del'] === '1' ? '1' : '0',
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
