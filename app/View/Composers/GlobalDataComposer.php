<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Enums\ColorScheme;
use App\Enums\ThemePreference;
use App\Models\Category;
use App\Models\Content;
use App\Models\User;
use App\Models\UsersRelease;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\SiteLogoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        private readonly SiteLogoService $siteLogoService,
        private readonly ConfigurationProvider $configuration,
    ) {}

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
        $site = $this->configuration->site();

        $viewData = [
            'serverroot' => url('/'),
            'site' => $site,
            'siteLogoUrl' => $this->siteLogoService->url($site->logoPath),
        ];

        // Cached useful links for sidebar
        $viewData['usefulLinks'] = $this->rememberWithCacheFallback('content_useful_links', self::CACHE_TTL, function () {
            return Content::active()
                ->ofType(Content::TYPE_USEFUL)
                ->ordered()
                ->get();
        });

        if (Auth::check()) {
            /** @var User $userdata */
            $userdata = Auth::user();
            $userId = (int) $userdata->getKey();

            $this->checkpointLastVisit($userdata, $userId);
            $userdata->categoryexclusions = User::getCachedCategoryExclusionById($userId);

            // Cached menu categories per user (depends on their exclusions)
            $parentcatlist = $this->rememberWithCacheFallback('menu_user_'.$userId, self::CACHE_TTL, function () use ($userdata) {
                return Category::getForMenu($userdata->categoryexclusions);
            });

            $viewData = array_merge($viewData, [
                'userdata' => $userdata,
                'loggedin' => true,
                'isadmin' => $userdata->hasRole('Admin'),
                'ismod' => $userdata->hasRole('Moderator'),
                'parentcatlist' => $parentcatlist,
                'header_menu_cat' => request()->input('t', ''),
                'cartCount' => UsersRelease::query()->where('users_id', $userId)->count(),
                'userTheme' => ThemePreference::fromPreference($userdata->theme_preference)->value,
                'userColorScheme' => ColorScheme::fromPreference($userdata->color_scheme)->value,
            ]);
        } else {
            $viewData = array_merge($viewData, [
                'isadmin' => false,
                'ismod' => false,
                'loggedin' => false,
                'userTheme' => ThemePreference::default()->value,
                'userColorScheme' => ColorScheme::default()->value,
            ]);
        }

        return $viewData;
    }

    private function checkpointLastVisit(User $user, int $userId): void
    {
        $checkpointAt = now();
        $cutoff = $checkpointAt->copy()->subHours(3);

        if ($user->lastlogin !== null && $user->lastlogin >= $cutoff) {
            return;
        }

        $userIp = config('nntmux_settings.store_user_ip') ? (request()->ip() ?? request()->getClientIp()) : '';

        User::query()
            ->whereKey($userId)
            ->where(static function (Builder $query) use ($cutoff): void {
                $query->whereNull('lastlogin')
                    ->orWhere('lastlogin', '<', $cutoff);
            })
            ->update([
                'lastlogin' => $checkpointAt,
                'host' => $userIp,
            ]);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function rememberWithCacheFallback(string $key, int|\DateInterval $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                Log::debug('GlobalDataComposer cache bypassed: '.$e->getMessage());
            }

            return $callback();
        }
    }
}
