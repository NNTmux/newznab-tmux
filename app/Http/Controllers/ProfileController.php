<?php

namespace App\Http\Controllers;

use App\Jobs\SendAccountDeletedEmail;
use App\Models\ReleaseComment;
use App\Models\RootCategory;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserRequest;
use App\Rules\ValidEmailDomain;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Jrean\UserVerification\Facades\UserVerification;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class ProfileController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function show(Request $request): mixed
    {

        $userID = $this->userdata->id;
        $privileged = $this->userdata->hasRole('Admin') || $this->userdata->hasRole('Moderator');
        $privateProfiles = config('nntmux_settings.private_profiles');
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

        if ($this->userdata === null) { // @phpstan-ignore identical.alwaysFalse
            return $this->show404('No such user!');
        }

        // Check if the user selected a theme.
        if (! isset($this->userdata->style) || $this->userdata->style === 'None') {
            $this->userdata->style = 'Using the admin selected theme.';
        }

        // Get 24-hour hourly data for downloads and API requests
        $downloadsHourly = UserDownload::getHourlyDownloads($userID);
        $apiRequestsHourly = UserRequest::getHourlyApiRequests($userID);

        // Get role limits
        $userRole = $this->userdata->roles->first();
        $downloadLimit = $userRole->downloadrequests ?? 0;
        $apiLimit = $userRole->apirequests ?? 0;

        $this->viewData = array_merge($this->viewData, [
            'downloadlist' => UserDownload::getDownloadRequestsForUser($userID),
            'apirequests' => UserRequest::getApiRequests($userID),
            'grabstoday' => UserDownload::getDownloadRequests($userID),
            'userinvitedby' => $this->userdata->invitedby ? User::find($this->userdata->invitedby) : null,
            'user' => $this->userdata,
            'privateprofiles' => $privateProfiles,
            'publicview' => $publicView,
            'privileged' => $privileged,
            'isadmin' => $this->userdata->hasRole('Admin'),
            'commentslist' => ReleaseComment::getCommentsForUserRange($userID),
            'downloadsHourly' => $downloadsHourly,
            'apiRequestsHourly' => $apiRequestsHourly,
            'downloadLimit' => $downloadLimit,
            'apiLimit' => $apiLimit,
            'meta_title' => 'View User Profile',
            'meta_keywords' => 'view,profile,user,details',
            'meta_description' => 'View User Profile for '.$this->userdata->username,
        ]);

        return view('profile.index', $this->viewData);
    }

    /**
     * @return Factory|\Illuminate\View\View|View|RedirectResponse
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {

        $action = $request->input('action') ?? 'view';

        $userid = $this->userdata->id;

        $errorStr = '';
        $success_2fa = $request->session()->get('success');
        $error_2fa = $request->session()->get('error');

        // Generate 2FA QR code URL if 2FA is set up but not enabled
        $google2fa_url = '';
        if ($this->userdata->passwordSecurity()->exists() && ! $this->userdata->passwordSecurity->google2fa_enable) {
            $google2fa_url = Google2FA::getQRCodeInline(
                config('app.name'),
                $this->userdata->email,
                $this->userdata->passwordSecurity->google2fa_secret
            );
        }

        switch ($action) {
            case 'newapikey':
                User::updateRssKey($userid);

                return redirect()->to('profile');
            case 'clearcookies':
                return redirect()->to('profileedit');
            case 'submit':
                $validator = Validator::make($request->all(), [
                    'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$userid, new ValidEmailDomain],
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
                        $this->userdata->xxxview ? 1 : 0,
                        $request->has('consoleview') ? 1 : 0,
                        $request->has('bookview') ? 1 : 0,
                        'None',
                    );

                    // Update theme preference
                    if ($request->has('theme_preference')) {
                        $themeValue = $request->input('theme_preference');
                        if (in_array($themeValue, ['light', 'dark', 'system'])) {
                            User::where('id', $userid)->update(['theme_preference' => $themeValue]);
                        }
                    }

                    // Update timezone preference
                    if ($request->has('timezone')) {
                        $timezoneValue = $request->input('timezone');
                        $validTimezones = array_merge(['UTC'], ...array_values(getAvailableTimezones()));
                        if (in_array($timezoneValue, $validTimezones)) {
                            User::where('id', $userid)->update(['timezone' => $timezoneValue]);
                        }
                    }

                    // Update excluded subcategories
                    $excludedCategories = $request->input('excluded_categories', []);
                    // Ensure all values are integers
                    $excludedCategories = array_map('intval', array_filter($excludedCategories, 'is_numeric'));
                    $this->userdata->syncExcludedCategories($excludedCategories);

                    // Clear the category exclusions cache for this user
                    \Illuminate\Support\Facades\Cache::forget('user_category_exclusions_'.$userid);

                    // Handle Console permission
                    if ($request->has('viewconsole')) {
                        if (! $this->userdata->hasDirectPermission('view console')) {
                            $this->userdata->givePermissionTo('view console');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view console')) {
                            $this->userdata->revokePermissionTo('view console');
                        }
                    }

                    // Handle Movies permission
                    if ($request->has('viewmovies')) {
                        if (! $this->userdata->hasDirectPermission('view movies')) {
                            $this->userdata->givePermissionTo('view movies');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view movies')) {
                            $this->userdata->revokePermissionTo('view movies');
                        }
                    }

                    // Handle Audio permission
                    if ($request->has('viewaudio')) {
                        if (! $this->userdata->hasDirectPermission('view audio')) {
                            $this->userdata->givePermissionTo('view audio');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view audio')) {
                            $this->userdata->revokePermissionTo('view audio');
                        }
                    }

                    // Handle PC/Games permission
                    if ($request->has('viewpc')) {
                        if (! $this->userdata->hasDirectPermission('view pc')) {
                            $this->userdata->givePermissionTo('view pc');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view pc')) {
                            $this->userdata->revokePermissionTo('view pc');
                        }
                    }

                    // Handle TV permission
                    if ($request->has('viewtv')) {
                        if (! $this->userdata->hasDirectPermission('view tv')) {
                            $this->userdata->givePermissionTo('view tv');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view tv')) {
                            $this->userdata->revokePermissionTo('view tv');
                        }
                    }

                    // Handle Adult permission
                    if ($request->has('viewadult')) {
                        if (! $this->userdata->hasDirectPermission('view adult')) {
                            $this->userdata->givePermissionTo('view adult');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view adult')) {
                            $this->userdata->revokePermissionTo('view adult');
                        }
                    }

                    // Handle Books permission
                    if ($request->has('viewbooks')) {
                        if (! $this->userdata->hasDirectPermission('view books')) {
                            $this->userdata->givePermissionTo('view books');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view books')) {
                            $this->userdata->revokePermissionTo('view books');
                        }
                    }

                    // Handle Other permission
                    if ($request->has('viewother')) {
                        if (! $this->userdata->hasDirectPermission('view other')) {
                            $this->userdata->givePermissionTo('view other');
                        }
                    } else {
                        if ($this->userdata->hasPermissionTo('view other')) {
                            $this->userdata->revokePermissionTo('view other');
                        }
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

                            return redirect()->to('login')->with('info', 'You will be able to login after you verify your new email address');
                        }
                    }

                    return redirect()->to('profile')->with('success', 'Profile changes saved');
                }
                break;

            case 'view':
            default:
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'error' => $errorStr,
            'user' => $this->userdata,
            'userexccat' => User::getCategoryExclusionById($userid),
            'userExcludedCategories' => $this->userdata->excludedCategories()->pluck('categories_id')->toArray(),
            'success_2fa' => $success_2fa,
            'error_2fa' => $error_2fa,
            'google2fa_url' => $google2fa_url,
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'publicview' => false,
            'privileged' => $this->userdata->hasRole('Admin') || $this->userdata->hasRole('Moderator'),
            'userinvitedby' => $this->userdata->invitedby ? User::find($this->userdata->invitedby) : null,
            'categoriesWithSubs' => RootCategory::with(['categories' => function ($query) {
                $query->where('status', 1)->orderBy('title');
            }])->where('status', 1)->orderBy('title')->get(),
            'meta_title' => 'Edit User Profile',
            'meta_keywords' => 'edit,profile,user,details',
            'meta_description' => 'Edit User Profile for '.$this->userdata->username,
        ]);

        return view('profile.edit', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function destroy(Request $request): Application|View|Factory|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $userId = $request->input('id');

        if ($userId !== null && (int) $userId === $this->userdata->id && ! $this->userdata->hasRole('Admin')) {
            $user = User::find($userId);
            SendAccountDeletedEmail::dispatch($user);
            Auth::logout();
            $user->delete();
        }

        if ($this->userdata->hasRole('Admin')) {
            return redirect()->to('profile');
        }

        return view('errors.503')->with('warning', 'Dont try to delete another user account!');
    }

    /**
     * Update user's dark mode preference
     */
    public function updateTheme(Request $request): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'theme_preference' => ['required', 'in:light,dark,system'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
        }

        $user->theme_preference = $request->input('theme_preference');
        $user->save();

        return response()->json(['success' => true, 'theme_preference' => $user->theme_preference]);
    }
}
