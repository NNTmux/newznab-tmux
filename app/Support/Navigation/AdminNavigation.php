<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The admin sidebar as data. Marks the current page (including edit/add pages under a
 * list, e.g. admin/user-edit under "User List") and builds the breadcrumb trail.
 */
final class AdminNavigation
{
    /**
     * @return list<array{label: string, icon: string, url?: string, children?: list<array{label: string, icon: string, url: string}>}>
     */
    public static function definition(): array
    {
        return [
            ['label' => __('Dashboard'), 'icon' => 'fa-gauge-high', 'url' => route('admin.index')],
            ['label' => __('Users'), 'icon' => 'fa-users', 'children' => [
                ['label' => __('User List'), 'icon' => 'fa-user-group', 'url' => url('/admin/user-list')],
                ['label' => __('Roles'), 'icon' => 'fa-user-tag', 'url' => url('/admin/role-list')],
                ['label' => __('Role History'), 'icon' => 'fa-clock-rotate-left', 'url' => url('/admin/user-role-history')],
                ['label' => __('Promotions'), 'icon' => 'fa-arrow-up', 'url' => url('/admin/promotions')],
                ['label' => __('Deleted Users'), 'icon' => 'fa-user-slash', 'url' => url('/admin/deleted-users')],
                ['label' => __('GDPR Requests'), 'icon' => 'fa-shield-halved', 'url' => route('admin.gdpr-requests.index')],
            ]],
            ['label' => __('Invitations'), 'icon' => 'fa-envelope-open-text', 'url' => url('/admin/invitations')],
            ['label' => __('Payments'), 'icon' => 'fa-credit-card', 'url' => url('/admin/payment-list')],
            ['label' => __('Content'), 'icon' => 'fa-file-lines', 'children' => [
                ['label' => __('Content List'), 'icon' => 'fa-list', 'url' => url('/admin/content-list')],
                ['label' => __('Add Content'), 'icon' => 'fa-circle-plus', 'url' => url('/admin/content-add')],
            ]],
            ['label' => __('Comments'), 'icon' => 'fa-comments', 'url' => url('/admin/comments-list')],
            ['label' => __('Releases'), 'icon' => 'fa-download', 'children' => [
                ['label' => __('Release List'), 'icon' => 'fa-list-ul', 'url' => url('/admin/release-list')],
                ['label' => __('Release Reports'), 'icon' => 'fa-flag', 'url' => url('/admin/release-reports')],
                ['label' => __('Failed Releases'), 'icon' => 'fa-triangle-exclamation', 'url' => url('/admin/failrel-list')],
                ['label' => __('Categories'), 'icon' => 'fa-folder-tree', 'url' => url('/admin/category-list')],
            ]],
            ['label' => __('Movies'), 'icon' => 'fa-film', 'children' => [
                ['label' => __('Movie List'), 'icon' => 'fa-video', 'url' => url('/admin/movie-list')],
                ['label' => __('Add Movie'), 'icon' => 'fa-circle-plus', 'url' => url('/admin/movie-add')],
            ]],
            ['label' => __('TV Shows'), 'icon' => 'fa-tv', 'children' => [
                ['label' => __('TV Shows List'), 'icon' => 'fa-list', 'url' => url('/admin/show-list')],
                ['label' => __('Add TV Show'), 'icon' => 'fa-circle-plus', 'url' => url('/admin/show-add')],
            ]],
            ['label' => __('Catalog'), 'icon' => 'fa-photo-film', 'children' => [
                ['label' => __('AniDB'), 'icon' => 'fa-dragon', 'url' => url('/admin/anidb-list')],
                ['label' => __('Games'), 'icon' => 'fa-gamepad', 'url' => url('/admin/game-list')],
                ['label' => __('Console'), 'icon' => 'fa-desktop', 'url' => url('/admin/console-list')],
                ['label' => __('Music'), 'icon' => 'fa-music', 'url' => url('/admin/music-list')],
                ['label' => __('Books'), 'icon' => 'fa-book', 'url' => url('/admin/book-list')],
            ]],
            ['label' => __('PreDB'), 'icon' => 'fa-database', 'url' => url('/admin/predb')],
            ['label' => __('Filtering'), 'icon' => 'fa-filter', 'children' => [
                ['label' => __('Binary Blacklist'), 'icon' => 'fa-ban', 'url' => url('/admin/binaryblacklist-list')],
                ['label' => __('Category Regexes'), 'icon' => 'fa-folder', 'url' => url('/admin/category_regexes-list')],
                ['label' => __('Collection Regexes'), 'icon' => 'fa-layer-group', 'url' => url('/admin/collection_regexes-list')],
                ['label' => __('Release Naming Regexes'), 'icon' => 'fa-tag', 'url' => url('/admin/release_naming_regexes-list')],
            ]],
            ['label' => __('Groups'), 'icon' => 'fa-server', 'children' => [
                ['label' => __('All Groups'), 'icon' => 'fa-list', 'url' => url('/admin/group-list')],
                ['label' => __('Active Groups'), 'icon' => 'fa-circle-check', 'url' => url('/admin/group-list-active')],
                ['label' => __('Inactive Groups'), 'icon' => 'fa-circle-pause', 'url' => url('/admin/group-list-inactive')],
                ['label' => __('Bulk Add Groups'), 'icon' => 'fa-square-plus', 'url' => url('/admin/group-bulk')],
            ]],
            ['label' => __('System'), 'icon' => 'fa-gear', 'children' => [
                ['label' => __('Site Settings'), 'icon' => 'fa-sliders', 'url' => route('admin.settings.show', ['domain' => 'site'])],
                ['label' => __('Registration Settings'), 'icon' => 'fa-id-card', 'url' => route('admin.settings.show', ['domain' => 'registration'])],
                ['label' => __('Ingestion Settings'), 'icon' => 'fa-download', 'url' => route('admin.settings.show', ['domain' => 'ingestion'])],
                ['label' => __('Post Processing Settings'), 'icon' => 'fa-gears', 'url' => route('admin.settings.show', ['domain' => 'post-processing'])],
                ['label' => __('Metadata Settings'), 'icon' => 'fa-tags', 'url' => route('admin.settings.show', ['domain' => 'metadata'])],
                ['label' => __('Registrations'), 'icon' => 'fa-user-plus', 'url' => route('admin.registrations.index')],
                ['label' => __('Tmux'), 'icon' => 'fa-terminal', 'url' => route('admin.settings.show', ['domain' => 'tmux'])],
                ['label' => __('Statistics'), 'icon' => 'fa-chart-column', 'url' => url('/admin/site-stats')],
                ['label' => __('Site Status'), 'icon' => 'fa-signal', 'url' => route('admin.status.index')],
                ['label' => __('Logs'), 'icon' => 'fa-file-lines', 'url' => route('admin.logs.index')],
                ['label' => __('Horizon'), 'icon' => 'fa-layer-group', 'url' => url(config('horizon.path', 'horizon'))],
                ['label' => __('Monitoring'), 'icon' => 'fa-chart-area', 'url' => route('admin.monitoring')],
            ]],
        ];
    }

    /**
     * The menu with `active` on links and `open` on the group holding the current page.
     *
     * @return list<array{label: string, icon: string, url?: string, active: bool, open: bool, children: list<array{label: string, icon: string, url: string, active: bool}>}>
     */
    public static function forRequest(Request $request): array
    {
        $definition = self::definition();
        $activeUrl = self::activeUrl($definition, $request);
        $menu = [];

        foreach ($definition as $group) {
            $children = array_map(
                static fn (array $child): array => array_merge($child, ['active' => $child['url'] === $activeUrl]),
                $group['children'] ?? [],
            );
            $active = isset($group['url']) && $group['url'] === $activeUrl;

            $menu[] = array_merge($group, [
                'active' => $active,
                'open' => in_array(true, array_column($children, 'active'), true),
                'children' => $children,
            ]);
        }

        return $menu;
    }

    /**
     * Breadcrumb labels for the current page, e.g. ['Users', 'User List'].
     *
     * @return list<string>
     */
    public static function trailFor(Request $request): array
    {
        foreach (self::forRequest($request) as $group) {
            if ($group['active']) {
                return [$group['label']];
            }

            foreach ($group['children'] as $child) {
                if ($child['active']) {
                    return [$group['label'], $child['label']];
                }
            }
        }

        return [];
    }

    /**
     * Same path first (pagination, filter and sort parameters are ignored, but a link's own
     * query parameters must be present); then the closest parent link for pages nested below a
     * menu page; otherwise an edit/add page whose prefix matches a "-list" page.
     *
     * @param  list<array{label: string, icon: string, url?: string, children?: list<array{label: string, icon: string, url: string}>}>  $definition
     */
    private static function activeUrl(array $definition, Request $request): ?string
    {
        $current = self::normalise($request->getRequestUri());
        $links = [];

        foreach ($definition as $group) {
            if (isset($group['url'])) {
                $links[] = $group['url'];
            }
            foreach ($group['children'] ?? [] as $child) {
                $links[] = $child['url'];
            }
        }

        $currentPath = Str::before($current, '?');
        parse_str((string) parse_url($request->getRequestUri(), PHP_URL_QUERY), $currentQuery);

        $bestMatch = null;
        $bestMatchScore = -1;
        foreach ($links as $url) {
            if (Str::before(self::normalise($url), '?') !== $currentPath) {
                continue;
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $linkQuery);
            if (self::queryContains($currentQuery, $linkQuery) && count($linkQuery) > $bestMatchScore) {
                $bestMatch = $url;
                $bestMatchScore = count($linkQuery);
            }
        }

        if ($bestMatch !== null) {
            return $bestMatch;
        }

        // A page below a menu page (a detail, create or edit screen) belongs to the closest parent link.
        $parentMatch = null;
        $parentMatchLength = 0;
        foreach ($links as $url) {
            $path = Str::before(self::normalise($url), '?');
            if (str_starts_with($currentPath, $path.'/') && strlen($path) > $parentMatchLength) {
                $parentMatch = $url;
                $parentMatchLength = strlen($path);
            }
        }

        if ($parentMatch !== null) {
            return $parentMatch;
        }

        foreach ($links as $url) {
            $path = Str::before(self::normalise($url), '?');
            if (str_ends_with($path, '-list') && Str::is(Str::beforeLast($path, '-list').'-*', $currentPath)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Whether every parameter of the link is present, with the same value, in the current request.
     *
     * @param  array<array-key, mixed>  $currentQuery
     * @param  array<array-key, mixed>  $linkQuery
     */
    private static function queryContains(array $currentQuery, array $linkQuery): bool
    {
        foreach ($linkQuery as $key => $value) {
            if (! array_key_exists($key, $currentQuery) || $currentQuery[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    private static function normalise(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }
}
