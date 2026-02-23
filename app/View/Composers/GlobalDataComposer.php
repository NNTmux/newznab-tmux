<?php

namespace App\View\Composers;

use App\Events\UserLoggedIn;
use App\Models\Category;
use App\Models\Content;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class GlobalDataComposer
{
    /**
     * Cache TTL in seconds (5 minutes).
     */
    private const CACHE_TTL = 300;

    /**
     * Resolved data for this request, so we only build it once
     * even when the composer matches multiple views (layout + child).
     *
     * @var array<string, mixed>|null
     */
    private static ?array $resolvedData = null;

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        if (self::$resolvedData === null) {
            self::$resolvedData = $this->resolveData();
        }

        $view->with(self::$resolvedData);
    }

    /**
     * Build the global view data (called once per request).
     *
     * @return array<string, mixed>
     */
    private function resolveData(): array
    {
        // Cached site settings (shared across all requests)
        $siteArray = Cache::remember('site_settings_array', self::CACHE_TTL, function () {
            return Settings::query()
                ->pluck('value', 'name')
                ->map(fn ($value) => Settings::convertValue($value))
                ->all();
        });

        $viewData = [
            'serverroot' => url('/'),
            'site' => $siteArray,
        ];

        // Cached useful links for sidebar
        $viewData['usefulLinks'] = Cache::remember('content_useful_links', self::CACHE_TTL, function () {
            return Content::active()
                ->ofType(Content::TYPE_USEFUL)
                ->orderBy('ordinal')
                ->get();
        });

        if (Auth::check()) {
            $userId = Auth::id();

            // User data with category exclusions (short cache per user)
            $userdata = Cache::remember('composer_user_'.$userId, self::CACHE_TTL, function () use ($userId) {
                $user = User::find($userId);
                $user->categoryexclusions = User::getCategoryExclusionById($userId);

                return $user;
            });

            // Update last login every 3 hours (outside of cache to avoid stale checks)
            if (now()->subHours(3) > $userdata->lastlogin) {
                event(new UserLoggedIn($userdata));
            }

            // Cached menu categories per user (depends on their exclusions)
            $parentcatlist = Cache::remember('menu_user_'.$userId, self::CACHE_TTL, function () use ($userdata) {
                return Category::getForMenu($userdata->categoryexclusions);
            });

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

        return $viewData;
    }
}
