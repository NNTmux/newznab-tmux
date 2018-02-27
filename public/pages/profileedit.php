<?php

use App\Models\User;
use Blacklight\NZBGet;
use Blacklight\SABnzbd;
use App\Models\Category;
use App\Models\Settings;
use Blacklight\utility\Utility;
use App\Models\UserExcludedCategory;

$sab = new SABnzbd($page);
$nzbGet = new NZBGet($page);

if (! User::isLoggedIn()) {
    $page->show403();
}

$action = $page->request->input('action') ?? 'view';

$userid = User::currentUserId();
$data = User::find($userid);
if (! $data) {
    $page->show404();
}

$errorStr = '';

switch ($action) {
    case 'newapikey':
        User::updateRssKey($userid);
        header('Location: profileedit');
        break;
    case 'clearcookies':
        $sab->unsetCookie();
        header('Location: profileedit');
        break;
    case 'submit':

        $data['email'] = $page->request->input('email');
        if ($page->request->has('saburl') && ! ends_with($page->request->input('saburl'), '/') && strlen(trim($page->request->input('saburl'))) > 0) {
            $page->request->merge(['saburl' => $page->request->input('saburl') . '/']);
        }

        if ($page->request->input('password') !== '' && $page->request->input('password') !== $page->request->input('confirmpassword')) {
            $errorStr = 'Password Mismatch';
        } elseif ($page->request->input('password') !== '' && ! User::isValidPassword($page->request->input('password'))) {
            $errorStr = 'Your password must be longer than eight characters, have at least 1 number, at least 1 capital and at least one lowercase letter';
        } elseif (! empty($page->request->input('nzbgeturl')) && $nzbGet->verifyURL($page->request->input('nzbgeturl')) === false) {
            $errorStr = 'The NZBGet URL you entered is invalid!';
        } elseif (! User::isValidEmail($page->request->input('email'))) {
            $errorStr = 'Your email is not a valid format.';
        } else {
            $res = User::getByEmail($page->request->input('email'));
            if ($res && (int) $res['id'] !== (int) $userid) {
                $errorStr = 'Sorry, the email is already in use.';
            } elseif ((empty($page->request->input('saburl')) && ! empty($page->request->input('sabapikey'))) || (! empty($page->request->input('saburl')) && empty($page->request->input('sabapikey')))) {
                $errorStr = 'Insert a SABnzdb URL and API key.';
            } else {
                if ($page->request->has('sasetting') && $page->request->input('sabsetting') === 2) {
                    $sab->setCookie($page->request->input('saburl'), $page->request->input('sabapikey'), $page->request->input('sabpriority'), $page->request->input('sabapikeytype'));
                }

                User::updateUser(
                    $userid,
                    $data['username'],
                    $page->request->input('email'),
                    $data['grabs'],
                    $data['user_roles_id'],
                    $data['notes'],
                    $data['invites'],
                    $page->request->has('movieview') ? 1 : 0,
                    $page->request->has('musicview') ? 1 : 0,
                    $page->request->has('gameview') ? 1 : 0,
                    $page->request->has('xxxview') ? 1 : 0,
                    $page->request->has('consoleview') ? 1 : 0,
                    $page->request->has('bookview') ? 1 : 0,
                    $page->request->input('queuetypeids'),
                    $page->request->input('nzbgeturl') ?? '',
                    $page->request->input('nzbgetusername') ?? '',
                    $page->request->input('nzbgetpassword') ?? '',
                    $page->request->has('saburl') ? Utility::trailingSlash($page->request->input('saburl')) : '',
                    $page->request->input('sabapikey') ?? '',
                    $page->request->input('sabpriority') ?? '',
                    $page->request->input('sabapikeytype') ?? '',
                    $page->request->input('nzbvortex_server_url') ?? '',
                    $page->request->input('nzbvortex_api_key') ?? '',
                    $page->request->input('cp_url') ?? '',
                    $page->request->input('cp_api') ?? '',
                    (int) Settings::settingValue('site.main.userselstyle') === 1 ? $page->request->input('style') : 'None'
                );

                $page->request->merge(['exccat' => (! $page->request->has('exccat') || ! is_array($page->request->input('exccat'))) ? [] : $page->request->input('exccat')]);
                UserExcludedCategory::addCategoryExclusions($userid, $page->request->input('exccat'));

                if ($page->request->input('password') !== '') {
                    User::updatePassword($userid, $page->request->input('password'));
                }

                header('Location:'.WWW_TOP.'/profile');
                die();
            }
        }
        break;

    case 'view':
    default:
        break;
}
if ((int) Settings::settingValue('site.main.userselstyle') === 1) {
    // Get the list of themes.
    $page->smarty->assign('themelist', Utility::getThemesList());
}

$page->smarty->assign('error', $errorStr);
$page->smarty->assign('user', $data);
$page->smarty->assign('userexccat', User::getCategoryExclusion($userid));

$page->smarty->assign('saburl_selected', $sab->url);
$page->smarty->assign('sabapikey_selected', $sab->apikey);

$page->smarty->assign('sabapikeytype_ids', [SABnzbd::API_TYPE_NZB, SABnzbd::API_TYPE_FULL]);
$page->smarty->assign('sabapikeytype_names', ['Nzb Api Key', 'Full Api Key']);
$page->smarty->assign('sabapikeytype_selected', ($sab->apikeytype === '') ? SABnzbd::API_TYPE_NZB : $sab->apikeytype);

$page->smarty->assign('sabpriority_ids', [SABnzbd::PRIORITY_FORCE, SABnzbd::PRIORITY_HIGH, SABnzbd::PRIORITY_NORMAL, SABnzbd::PRIORITY_LOW, SABnzbd::PRIORITY_PAUSED]);
$page->smarty->assign('sabpriority_names', ['Force', 'High', 'Normal', 'Low', 'Paused']);
$page->smarty->assign('sabpriority_selected', ($sab->priority === '') ? SABnzbd::PRIORITY_NORMAL : $sab->priority);

$page->smarty->assign('sabsetting_ids', [1, 2]);
$page->smarty->assign('sabsetting_names', ['Site', 'Cookie']);
$page->smarty->assign('sabsetting_selected', ($sab->checkCookie() === true ? 2 : 1));

switch ($sab->integrated) {
    case SABnzbd::INTEGRATION_TYPE_USER:
        $queueTypes = ['None', 'Sabnzbd', 'NZBGet'];
        $queueTypeIDs = [User::QUEUE_NONE, User::QUEUE_SABNZBD, User::QUEUE_NZBGET];
        break;
    case SABnzbd::INTEGRATION_TYPE_NONE:
        $queueTypes = ['None', 'NZBGet'];
        $queueTypeIDs = [User::QUEUE_NONE, User::QUEUE_NZBGET];
        break;
}

$page->smarty->assign(
    [
        'queuetypes'   => $queueTypes,
        'queuetypeids' => $queueTypeIDs,
    ]
);

$page->meta_title = 'Edit User Profile';
$page->meta_keywords = 'edit,profile,user,details';
$page->meta_description = 'Edit User Profile for '.$data['username'];

$page->smarty->assign('cp_url_selected', $data['cp_url']);
$page->smarty->assign('cp_api_selected', $data['cp_api']);

$page->smarty->assign('catlist', Category::getForSelect(false));

$page->content = $page->smarty->fetch('profileedit.tpl');
$page->render();
