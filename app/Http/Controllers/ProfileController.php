<?php

namespace App\Http\Controllers;

use App\Models\User;
use Blacklight\NZBGet;
use Blacklight\SABnzbd;
use App\Models\Category;
use App\Models\Settings;
use App\Models\UserRequest;
use App\Mail\AccountDeleted;
use App\Models\UserDownload;
use Illuminate\Http\Request;
use App\Models\ReleaseComment;
use Blacklight\utility\Utility;
use App\Models\UserExcludedCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Jrean\UserVerification\Facades\UserVerification;

class ProfileController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPrefs();
        $sab = new SABnzbd($this);

        $userID = $this->userdata->id;
        $privileged = $this->userdata->hasRole('Admin') === true || $this->userdata->hasRole('Moderator');
        $privateProfiles = (int) Settings::settingValue('..privateprofiles') === 1;
        $publicView = false;

        if ($privileged || ! $privateProfiles) {
            $altID = ($request->has('id') && (int) $request->input('id') >= 0) ? (int) $request->input('id') : false;
            $altUsername = ($request->has('name') && \strlen($request->input('name')) > 0) ? $request->input('name') : false;

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

        $downloadList = UserDownload::getDownloadRequestsForUser($userID);
        $this->smarty->assign('downloadlist', $downloadList);

        $data = User::find($userID);
        if ($data === null) {
            $this->show404('No such user!');
        }

        // Check if the user selected a theme.
        if (! isset($data['style']) || $data['style'] === 'None') {
            $data['style'] = 'Using the admin selected theme.';
        }
        $this->smarty->assign(
            [
                'apirequests'       => UserRequest::getApiRequests($userID),
                'grabstoday'        => UserDownload::getDownloadRequests($userID),
                'userinvitedby'     => $data['invitedby'] !== '' ? User::find($data['invitedby']) : '',
                'user'              => $data,
                'privateprofiles'   => $privateProfiles,
                'publicview'        => $publicView,
                'privileged'        => $privileged,
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
        $this->smarty->assign(
            [
                'commentslist'  => ReleaseComment::getCommentsForUserRange($userID),
                'exccats'       => implode(',', UserExcludedCategory::getCategoryExclusionNames($userID)),
                'saburl'        => $sab->url,
                'sabapikey'     => $sab->apikey,
                'sabapikeytype' => $sab->apikeytype !== '' ? $sabApiKeyTypes[$sab->apikeytype] : '',
                'sabpriority'   => $sab->priority !== '' ? $sabPriorities[$sab->priority] : '',
                'sabsetting'    => $sabSettings[$sab->checkCookie() === true ? 2 : 1],
            ]
        );

        $meta_title = 'View User Profile';
        $meta_keywords = 'view,profile,user,details';
        $meta_description = 'View User Profile for '.$data['username'];

        $content = $this->smarty->fetch('profile.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setPrefs();
        $sab = new SABnzbd($this);
        $nzbGet = new NZBGet($this);

        $action = $request->input('action') ?? 'view';

        $userid = Auth::id();
        $data = User::find($userid);
        if (! $data) {
            $this->show404('No such user!');
        }

        $errorStr = '';

        switch ($action) {
            case 'newapikey':
                User::updateRssKey($userid);

                return redirect('profile');
                break;
            case 'clearcookies':
                $sab->unsetCookie();

                return redirect('profileedit');
                break;
            case 'submit':

                if ($request->has('saburl') && ! ends_with($request->input('saburl'), '/') && \strlen(trim($request->input('saburl'))) > 0) {
                    $request->merge(['saburl' => $request->input('saburl').'/']);
                }

                if ($request->has('password') && $request->input('password') !== $request->input('password_confirmation')) {
                    $errorStr = 'Password Mismatch';
                } elseif ($request->has('password') && ! empty($request->input('password')) && ! User::isValidPassword($request->input('password'))) {
                    $errorStr = 'Your password must be longer than eight characters, have at least 1 number, at least 1 capital and at least one lowercase letter';
                } elseif (! empty($request->input('nzbgeturl')) && $nzbGet->verifyURL($request->input('nzbgeturl')) === false) {
                    $errorStr = 'The NZBGet URL you entered is invalid!';
                } elseif (! User::isValidEmail($request->input('email'))) {
                    $errorStr = 'Your email is not a valid format.';
                } else {
                    $res = User::getByEmail($request->input('email'));
                    if ($res && (int) $res['id'] !== $userid) {
                        $errorStr = 'Sorry, the email is already in use.';
                    } elseif ((! $request->has('saburl') && $request->has('sabapikey')) || ($request->has('saburl') && ! $request->has('sabapikey'))) {
                        $errorStr = 'Insert a SABnzdb URL and API key.';
                    } else {
                        if ($request->has('sasetting') && $request->input('sabsetting') === 2) {
                            $sab->setCookie($request->input('saburl'), $request->input('sabapikey'), $request->input('sabpriority'), $request->input('sabapikeytype'));
                        }

                        $user = User::find($userid);

                        User::updateUser(
                            $userid,
                            $data['username'],
                            $request->input('email'),
                            $data['grabs'],
                            $data['roles_id'],
                            $data['notes'],
                            $data['invites'],
                            $request->has('movieview') ? 1 : 0,
                            $request->has('musicview') ? 1 : 0,
                            $request->has('gameview') ? 1 : 0,
                            $request->has('xxxview') ? 1 : 0,
                            $request->has('consoleview') ? 1 : 0,
                            $request->has('bookview') ? 1 : 0,
                            $request->input('queuetypeids'),
                            $request->input('nzbgeturl') ?? '',
                            $request->input('nzbgetusername') ?? '',
                            $request->input('nzbgetpassword') ?? '',
                            $request->has('saburl') ? str_finish($request->input('saburl'), '/') : '',
                            $request->input('sabapikey') ?? '',
                            $request->input('sabpriority') ?? '',
                            $request->input('sabapikeytype') ?? '',
                            $request->input('nzbvortex_server_url') ?? '',
                            $request->input('nzbvortex_api_key') ?? '',
                            $request->input('cp_url') ?? '',
                            $request->input('cp_api') ?? '',
                            (int) Settings::settingValue('site.main.userselstyle') === 1 ? $request->input('style') : 'None'
                        );

                        if ((int) $request->input('viewconsole') === 1 && $user->role->hasPermissionTo('view console') === true && $user->hasDirectPermission('view console') === false) {
                            $user->givePermissionTo('view console');
                        } elseif ((int) $request->input('viewconsole') === 0 && $user->role->hasPermissionTo('view console') === true && $user->hasPermissionTo('view console') === true) {
                            $user->revokePermissionTo('view console');
                        } elseif ($user->role->hasPermissionTo('view console') === false && $user->hasDirectPermission('view console') === true && ((int) $request->input('viewconsole') === 0 || (int) $request->input('viewconsole') === 1)) {
                            $user->revokePermissionTo('view console');
                        }

                        if ((int) $request->input('viewmovies') === 1 && $user->role->hasPermissionTo('view movies') === true && $user->hasDirectPermission('view movies') === false) {
                            $user->givePermissionTo('view movies');
                        } elseif ((int) $request->input('viewmovies') === 0 && $user->role->hasPermissionTo('view movies') === true && $user->hasDirectPermission('view movies') === true) {
                            $user->revokePermissionTo('view movies');
                        } elseif ($user->role->hasPermissionTo('view movies') === false && $user->hasDirectPermission('view movies') === true && ((int) $request->input('viewmovies') === 0 || (int) $request->input('viewmovies') === 1)) {
                            $user->revokePermissionTo('view movies');
                        }

                        if ((int) $request->input('viewaudio') === 1 && $user->role->hasPermissionTo('view audio') === true && $user->hasDirectPermission('view audio') === false) {
                            $user->givePermissionTo('view audio');
                        } elseif ((int) $request->input('viewaudio') === 0 && $user->role->hasPermissionTo('view audio') === true && $user->hasDirectPermission('view audio') === true) {
                            $user->revokePermissionTo('view audio');
                        } elseif ($user->role->hasPermissionTo('view audio') === false && $user->hasDirectPermission('view audio') === true && ((int) $request->input('viewaudio') === 0 || (int) $request->input('viewaudio') === 1)) {
                            $user->revokePermissionTo('view audio');
                        }

                        if ((int) $request->input('viewpc') === 1 && $user->role->hasPermissionTo('view pc') === true && $user->hasDirectPermission('view pc') === false) {
                            $user->givePermissionTo('view pc');
                        } elseif ((int) $request->input('viewpc') === 0 && $user->role->hasPermissionTo('view pc') === true && $user->hasDirectPermission('view pc') === true) {
                            $user->revokePermissionTo('view pc');
                        } elseif ($user->role->hasPermissionTo('view pc') === false && $user->hasDirectPermission('view pc') === true && ((int) $request->input('viewpc') === 0 || (int) $request->input('viewpc') === 1)) {
                            $user->revokePermissionTo('view pc');
                        }

                        if ((int) $request->input('viewtv') === 1 && $user->role->hasPermissionTo('view tv') === true && $user->hasDirectPermission('view tv') === false) {
                            $user->givePermissionTo('view tv');
                        } elseif ((int) $request->input('viewtv') === 0 && $user->role->hasPermissionTo('view tv') === true && $user->hasDirectPermission('view tv') === true) {
                            $user->revokePermissionTo('view tv');
                        } elseif ($user->role->hasPermissionTo('view tv') === false && $user->hasDirectPermission('view tv') === true && ((int) $request->input('viewtv') === 0 || (int) $request->input('viewtv') === 1)) {
                            $user->revokePermissionTo('view tv');
                        }

                        if ((int) $request->input('viewadult') === 1 && $user->role->hasPermissionTo('view adult') === true && $user->hasDirectPermission('view adult') === false) {
                            $user->givePermissionTo('view adult');
                        } elseif ((int) $request->input('viewadult') === 0 && $user->role->hasPermissionTo('view adult') === true && $user->hasDirectPermission('view adult') === true) {
                            $user->revokePermissionTo('view adult');
                        } elseif ($user->role->hasPermissionTo('view adult') === false && $user->hasDirectPermission('view adult') === true && ((int) $request->input('viewadult') === 0 || (int) $request->input('viewadult') === 1)) {
                            $user->revokePermissionTo('view adult');
                        }

                        if ((int) $request->input('viewbooks') === 1 && $user->role->hasPermissionTo('view books') === true && $user->hasDirectPermission('view books') === false) {
                            $user->givePermissionTo('view books');
                        } elseif ((int) $request->input('viewbooks') === 0 && $user->role->hasPermissionTo('view books') === true && $user->hasDirectPermission('view books') === true) {
                            $user->revokePermissionTo('view books');
                        } elseif ($user->role->hasPermissionTo('view books') === false && $user->hasDirectPermission('view books') === true && ((int) $request->input('viewbooks') === 0 || (int) $request->input('viewbooks') === 1)) {
                            $user->revokePermissionTo('view books');
                        }

                        if ((int) $request->input('viewother') === 1 && $user->role->hasPermissionTo('view other') === true && $user->hasDirectPermission('view other') === false) {
                            $user->givePermissionTo('view other');
                        } elseif ((int) $request->input('viewother') === 0 && $user->role->hasPermissionTo('view other') === true && $user->hasDirectPermission('view other') === true) {
                            $user->revokePermissionTo('view other');
                        } elseif ($user->role->hasPermissionTo('view other') === false && $user->hasDirectPermission('view other') === true && ((int) $request->input('viewother') === 0 || (int) $request->input('viewother') === 1)) {
                            $user->revokePermissionTo('view other');
                        }

                        if ($request->has('password') && ! empty($request->input('password'))) {
                            User::updatePassword($userid, $request->input('password'));
                        }

                        if ($data['email'] !== $request->input('email')) {
                            $data['email'] = $request->input('email');

                            UserVerification::generate($data);

                            UserVerification::send($data, 'User email verification required');
                        }

                        return redirect('profile');
                    }
                }
                break;

            case 'view':
            default:
                break;
        }
        if ((int) Settings::settingValue('site.main.userselstyle') === 1) {
            // Get the list of themes.
            $this->smarty->assign('themelist', Utility::getThemesList());
        }

        $this->smarty->assign('error', $errorStr);
        $this->smarty->assign('user', $data);
        $this->smarty->assign('userexccat', User::getCategoryExclusion($userid));

        $this->smarty->assign('saburl_selected', $sab->url);
        $this->smarty->assign('sabapikey_selected', $sab->apikey);

        $this->smarty->assign('sabapikeytype_ids', [SABnzbd::API_TYPE_NZB, SABnzbd::API_TYPE_FULL]);
        $this->smarty->assign('sabapikeytype_names', ['Nzb Api Key', 'Full Api Key']);
        $this->smarty->assign('sabapikeytype_selected', ($sab->apikeytype === '') ? SABnzbd::API_TYPE_NZB : $sab->apikeytype);

        $this->smarty->assign('sabpriority_ids', [SABnzbd::PRIORITY_FORCE, SABnzbd::PRIORITY_HIGH, SABnzbd::PRIORITY_NORMAL, SABnzbd::PRIORITY_LOW, SABnzbd::PRIORITY_PAUSED]);
        $this->smarty->assign('sabpriority_names', ['Force', 'High', 'Normal', 'Low', 'Paused']);
        $this->smarty->assign('sabpriority_selected', ($sab->priority === '') ? SABnzbd::PRIORITY_NORMAL : $sab->priority);

        $this->smarty->assign('sabsetting_ids', [1, 2]);
        $this->smarty->assign('sabsetting_names', ['Site', 'Cookie']);
        $this->smarty->assign('sabsetting_selected', ($sab->checkCookie() === true ? 2 : 1));

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

        $this->smarty->assign(
            [
                'queuetypes'   => $queueTypes,
                'queuetypeids' => $queueTypeIDs,
            ]
        );

        $meta_title = 'Edit User Profile';
        $meta_keywords = 'edit,profile,user,details';
        $meta_description = 'Edit User Profile for '.$data['username'];

        $this->smarty->assign('cp_url_selected', $data['cp_url']);
        $this->smarty->assign('cp_api_selected', $data['cp_api']);
        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('catlist', Category::getForSelect(false));

        $content = $this->smarty->fetch('profileedit.tpl');

        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );
        $this->pagerender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function destroy(Request $request)
    {
        $this->setPrefs();
        $userId = $request->input('id');

        if ($userId !== null && $this->userdata->hasRole('Admin') === false && (int) $userId === Auth::id()) {
            Mail::to(Settings::settingValue('site.main.email'))->send(new AccountDeleted($userId));
            User::deleteUser($userId);

            return redirect('login');
        }

        if ($this->userdata->hasRole('Admin')) {
            return redirect('profile');
        }

        return view('errors.badboy')->with('Message', 'Dont try to delete another user account!');
    }
}
