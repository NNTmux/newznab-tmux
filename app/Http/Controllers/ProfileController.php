<?php

namespace App\Http\Controllers;

use App\Jobs\SendAccountDeletedEmail;
use App\Models\ReleaseComment;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Jrean\UserVerification\Facades\UserVerification;

class ProfileController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function show(Request $request)
    {
        $this->setPreferences();

        $userID = $this->userdata->id;
        $privileged = $this->userdata->hasRole('Admin') || $this->userdata->hasRole('Moderator');
        $privateProfiles = (int) Settings::settingValue('..privateprofiles') === 1;
        $publicView = false;

        if ($privileged || ! $privateProfiles) {
            $altID = ($request->has('id') && (int) $request->input('id') >= 0) ? (int) $request->input('id') : false;
            $altUsername = ($request->has('name') && $request->input('name') !== '') ? $request->input('name') : false;

            // If both 'id' and 'name' are specified, 'id' should take precedence.
            if ($altID === false && $altUsername !== false) {
                $user = User::getByUsername($altUsername);
                if ($user) {
                    $this->userdata = $user;
                    $altID = $user['id'];
                    $userID = $altID;
                }
            } elseif ($altID !== false) {
                $user = User::find($altID);
                if ($user) {
                    $this->userdata = $user;
                    $userID = $altID;
                    $publicView = true;
                }
            }
        }

        $downloadList = UserDownload::getDownloadRequestsForUser($userID);
        $this->smarty->assign('downloadlist', $downloadList);

        if ($this->userdata === null) {
            $this->show404('No such user!');
        }

        // Check if the user selected a theme.
        if (! isset($this->userdata->style) || $this->userdata->style === 'None') {
            $this->userdata->style = 'Using the admin selected theme.';
        }
        $this->smarty->assign(
            [
                'apirequests' => UserRequest::getApiRequests($userID),
                'grabstoday' => UserDownload::getDownloadRequests($userID),
                'userinvitedby' => $this->userdata->invitedby !== '' ? User::find($this->userdata->invitedby) : '',
                'user' => $this->userdata,
                'privateprofiles' => $privateProfiles,
                'publicview' => $publicView,
                'privileged' => $privileged,
            ]
        );

        // Pager must be fetched after the variables are assigned to smarty.
        $this->smarty->assign(
            [
                'commentslist' => ReleaseComment::getCommentsForUserRange($userID),
            ]
        );

        $meta_title = 'View User Profile';
        $meta_keywords = 'view,profile,user,details';
        $meta_description = 'View User Profile for '.$this->userdata->username;

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
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setPreferences();

        $action = $request->input('action') ?? 'view';

        $userid = $this->userdata->id;
        if (! $this->userdata) {
            $this->show404('No such user!');
        }

        $errorStr = '';

        switch ($action) {
            case 'newapikey':
                User::updateRssKey($userid);

                return redirect('profile');
            case 'clearcookies':
                return redirect('profileedit');
            case 'submit':
                $validator = Validator::make($request->all(), [
                    'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users', 'indisposable'],
                    'password' => ['nullable', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'],
                ]);

                if ($validator->fails()) {
                    $errorStr = implode('', Arr::collapse($validator->errors()->toArray()));
                } else {
                    User::updateUser(
                        $userid,
                        $this->userdata->username,
                        $request->input('email'),
                        $this->userdata->grabs,
                        $this->userdata->roles_id,
                        $this->userdata->notes,
                        $this->userdata->invites,
                        $request->has('movieview') ? 1 : 0,
                        $request->has('musicview') ? 1 : 0,
                        $request->has('gameview') ? 1 : 0,
                        $request->has('xxxview') ? 1 : 0,
                        $request->has('consoleview') ? 1 : 0,
                        $request->has('bookview') ? 1 : 0,
                        (int) Settings::settingValue('site.main.userselstyle') === 1 ? $request->input('style') : 'None'
                    );

                    if ((int) $request->input('viewconsole') === 1 && $this->userdata->can('view console') && ! $this->userdata->hasDirectPermission('view console')) {
                        $this->userdata->givePermissionTo('view console');
                    } elseif ((int) $request->input('viewconsole') === 0 && $this->userdata->can('view console') && $this->userdata->hasDirectPermission('view console')) {
                        $this->userdata->revokePermissionTo('view console');
                    } elseif ($this->userdata->cant('view console') && \in_array((int) $request->input('viewconsole'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view console');
                    }

                    if ((int) $request->input('viewmovies') === 1 && $this->userdata->can('view movies') && ! $this->userdata->hasDirectPermission('view movies')) {
                        $this->userdata->givePermissionTo('view movies');
                    } elseif ((int) $request->input('viewmovies') === 0 && $this->userdata->can('view movies') && $this->userdata->hasDirectPermission('view movies')) {
                        $this->userdata->revokePermissionTo('view movies');
                    } elseif ($this->userdata->cant('view movies') && $this->userdata->hasDirectPermission('view movies') && \in_array((int) $request->input('viewmovies'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view movies');
                    }

                    if ((int) $request->input('viewaudio') === 1 && $this->userdata->can('view audio') && ! $this->userdata->hasDirectPermission('view audio')) {
                        $this->userdata->givePermissionTo('view audio');
                    } elseif ((int) $request->input('viewaudio') === 0 && $this->userdata->can('view audio') && $this->userdata->hasDirectPermission('view audio')) {
                        $this->userdata->revokePermissionTo('view audio');
                    } elseif ($this->userdata->cant('view audio') && $this->userdata->hasDirectPermission('view audio') && \in_array((int) $request->input('viewaudio'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view audio');
                    }

                    if ((int) $request->input('viewpc') === 1 && $this->userdata->can('view pc') && ! $this->userdata->hasDirectPermission('view pc')) {
                        $this->userdata->givePermissionTo('view pc');
                    } elseif ((int) $request->input('viewpc') === 0 && $this->userdata->can('view pc') && $this->userdata->hasDirectPermission('view pc')) {
                        $this->userdata->revokePermissionTo('view pc');
                    } elseif ($this->userdata->cant('view pc') && $this->userdata->hasDirectPermission('view pc') && \in_array((int) $request->input('viewpc'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view pc');
                    }

                    if ((int) $request->input('viewtv') === 1 && $this->userdata->can('view tv') && ! $this->userdata->hasDirectPermission('view tv')) {
                        $this->userdata->givePermissionTo('view tv');
                    } elseif ((int) $request->input('viewtv') === 0 && $this->userdata->can('view tv') && $this->userdata->hasDirectPermission('view tv')) {
                        $this->userdata->revokePermissionTo('view tv');
                    } elseif ($this->userdata->cant('view tv') && $this->userdata->hasDirectPermission('view tv') && \in_array((int) $request->input('viewtv'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view tv');
                    }

                    if ((int) $request->input('viewadult') === 1 && $this->userdata->can('view adult') && ! $this->userdata->hasDirectPermission('view adult')) {
                        $this->userdata->givePermissionTo('view adult');
                    } elseif ((int) $request->input('viewadult') === 0 && $this->userdata->can('view adult') && $this->userdata->hasDirectPermission('view adult')) {
                        $this->userdata->revokePermissionTo('view adult');
                    } elseif ($this->userdata->cant('view adult') && $this->userdata->hasDirectPermission('view adult') && \in_array((int) $request->input('viewadult'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view adult');
                    }

                    if ((int) $request->input('viewbooks') === 1 && $this->userdata->can('view books') && ! $this->userdata->hasDirectPermission('view books')) {
                        $this->userdata->givePermissionTo('view books');
                    } elseif ((int) $request->input('viewbooks') === 0 && $this->userdata->can('view books') && $this->userdata->hasDirectPermission('view books')) {
                        $this->userdata->revokePermissionTo('view books');
                    } elseif ($this->userdata->cant('view books') && $this->userdata->hasDirectPermission('view books') && \in_array((int) $request->input('viewbooks'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view books');
                    }

                    if ((int) $request->input('viewother') === 1 && $this->userdata->can('view other') && ! $this->userdata->hasDirectPermission('view other')) {
                        $this->userdata->givePermissionTo('view other');
                    } elseif ((int) $request->input('viewother') === 0 && $this->userdata->can('view other') && $this->userdata->hasDirectPermission('view other')) {
                        $this->userdata->revokePermissionTo('view other');
                    } elseif ($this->userdata->cant('view other') && $this->userdata->hasDirectPermission('view other') && \in_array((int) $request->input('viewother'), [0, 1], true)) {
                        $this->userdata->revokePermissionTo('view other');
                    }

                    if ($request->has('password') && ! empty($request->input('password'))) {
                        User::updatePassword($userid, $request->input('password'));
                    }

                    if (! $this->userdata->hasRole('Admin')) {
                        if (! empty($request->input('email')) && $this->userdata->email !== $request->input('email')) {
                            $this->userdata->email = $request->input('email');

                            $verify_user = $this->userdata;

                            UserVerification::generate($verify_user);

                            UserVerification::send($verify_user, 'User email verification required');

                            Auth::logout();

                            return redirect('login')->with('info', 'You will be able to login after you verify your new email address');
                        }
                    }

                    return redirect('profile')->with('success', 'Profile changes saved');
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
        $this->smarty->assign('user', $this->userdata);
        $this->smarty->assign('userexccat', User::getCategoryExclusionById($userid));

        $meta_title = 'Edit User Profile';
        $meta_keywords = 'edit,profile,user,details';
        $meta_description = 'Edit User Profile for '.$this->userdata->username;

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $content = $this->smarty->fetch('profileedit.tpl');

        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function destroy(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Routing\Redirector|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $this->setPreferences();
        $userId = $request->input('id');

        if ($userId !== null && (int) $userId === $this->userdata->id && ! $this->userdata->hasRole('Admin')) {
            $user = User::find($userId);
            SendAccountDeletedEmail::dispatch($user);
            Auth::logout();
            $user->delete();
        }

        if ($this->userdata->hasRole('Admin')) {
            return redirect('profile');
        }

        return view('errors.badboy')->with('warning', 'Dont try to delete another user account!');
    }
}
