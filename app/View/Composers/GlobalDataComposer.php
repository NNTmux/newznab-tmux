<?php

namespace App\View\Composers;

use App\Events\UserLoggedIn;
use App\Models\Category;
use App\Models\Content;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GlobalDataComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $viewName = $view->getName() ?? '';
        $isEmailView = str_starts_with($viewName, 'emails.');

        // Load settings as array with type conversions (empty strings -> null, numeric strings -> numbers)
        $siteArray = Settings::query()
            ->pluck('value', 'name')
            ->map(fn ($value) => Settings::convertValue($value))
            ->all();

        $viewData = [
            'serverroot' => url('/'),
        ];

        // Email views expect $site to be the string provided by the mailable
        if (! $isEmailView) {
            $viewData['site'] = $siteArray;  // Now it's a proper array, not a Settings model
        }

        // Load useful links for sidebar
        $usefulLinks = Content::active()
            ->ofType(Content::TYPE_USEFUL)
            ->orderBy('ordinal')
            ->get();
        $viewData['usefulLinks'] = $usefulLinks;

        if (Auth::check()) {
            $userdata = User::find(Auth::id());
            $userdata->categoryexclusions = User::getCategoryExclusionById(Auth::id());

            // Update last login every 15 mins.
            if (now()->subHours(3) > $userdata->lastlogin) {
                event(new UserLoggedIn($userdata));
            }

            $parentcatlist = Category::getForMenu($userdata->categoryexclusions);

            $viewData = array_merge($viewData, [
                'userdata' => $userdata,
                'loggedin' => true,
                'isadmin' => $userdata->hasRole('Admin'),
                'ismod' => $userdata->hasRole('Moderator'),
                'parentcatlist' => $parentcatlist,
                'header_menu_cat' => request()->input('t', ''),
            ]);
        } else {
            $viewData = array_merge($viewData, [
                'isadmin' => false,
                'ismod' => false,
                'loggedin' => false,
            ]);
        }

        $view->with($viewData);
    }
}
