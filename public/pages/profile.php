<?php

use App\Models\User;
use Blacklight\NZBGet;
use Blacklight\SABnzbd;
use App\Models\Settings;
use App\Models\UserRequest;
use App\Models\UserDownload;
use App\Models\ReleaseComment;
use App\Models\UserExcludedCategory;

if (! User::isLoggedIn()) {
    $page->show403();
}

$sab = new SABnzbd($page);
$nzbget = new NZBGet($page);

$userID = User::currentUserId();
$privileged = User::isAdmin($userID) || User::isModerator($userID);
$privateProfiles = (int) Settings::settingValue('..privateprofiles') === 1;
$publicView = false;

if ($privileged || ! $privateProfiles) {
    $altID = (\request()->has('id') && (int) \request()->input('id') >= 0) ? (int) \request()->input('id') : false;
    $altUsername = (\request()->has('name') && strlen(\request()->input('name')) > 0) ? \request()->input('name') : false;

    // If both 'id' and 'name' are specified, 'id' should take precedence.
    if ($altID === false && $altUsername !== false) {
        $user = User::getByUsername($altUsername);
        if ($user) {
            $altID = $user['id'];
            $userID = $altID;
        }
    } elseif ($altID !== false) {
        $userID = $altID;
        $publicView = true;
    }
}

$downloadlist = UserDownload::getDownloadRequestsForUser($userID);
$page->smarty->assign('downloadlist', $downloadlist);

$data = User::find($userID);
if (! $data) {
    $page->show404();
}

// Check if the user selected a theme.
if (! isset($data['style']) || $data['style'] === 'None') {
    $data['style'] = 'Using the admin selected theme.';
}

$offset = \request()->input('offset') ?? 0;
$page->smarty->assign(
    [
        'apirequests'       => UserRequest::getApiRequests($userID),
        'grabstoday'        => UserDownload::getDownloadRequests($userID),
        'userinvitedby'     => $data['invitedby'] !== '' ? User::find($data['invitedby']) : '',
        'user'              => $data,
        'privateprofiles'   => $privateProfiles,
        'publicview'        => $publicView,
        'privileged'        => $privileged,
        'pagertotalitems'   => ReleaseComment::getCommentCountForUser($userID),
        'pageroffset'       => $offset,
        'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
        'pagerquerybase'    => '/profile?id='.$userID.'&offset=',
        'pagerquerysuffix'  => '#comments',
    ]
);

$sabApiKeyTypes = [
    SABnzbd::API_TYPE_NZB => 'Nzb Api Key',
    SABnzbd::API_TYPE_FULL => 'Full Api Key',
];
$sabPriorities = [
    SABnzbd::PRIORITY_FORCE  => 'Force', SABnzbd::PRIORITY_HIGH => 'High',
    SABnzbd::PRIORITY_NORMAL => 'Normal', SABnzbd::PRIORITY_LOW => 'Low',
];
$sabSettings = [1 => 'Site', 2 => 'Cookie'];

// Pager must be fetched after the variables are assigned to smarty.
$page->smarty->assign(
    [
        'pager'         => $page->smarty->fetch('pager.tpl'),
        'commentslist'  => ReleaseComment::getCommentsForUserRange($userID, $offset, env('ITEMS_PER_PAGE', 50)),
        'exccats'       => implode(',', UserExcludedCategory::getCategoryExclusionNames($userID)),
        'saburl'        => $sab->url,
        'sabapikey'     => $sab->apikey,
        'sabapikeytype' => $sab->apikeytype !== '' ? $sabApiKeyTypes[$sab->apikeytype] : '',
        'sabpriority'   => $sab->priority !== '' ? $sabPriorities[$sab->priority] : '',
        'sabsetting'    => $sabSettings[$sab->checkCookie() === true ? 2 : 1],
    ]
);

$page->meta_title = 'View User Profile';
$page->meta_keywords = 'view,profile,user,details';
$page->meta_description = 'View User Profile for '.$data['username'];

$page->content = $page->smarty->fetch('profile.tpl');
$page->render();
