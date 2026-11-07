<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\DownloadStat;
use App\Models\GrabStat;
use App\Models\ReleaseStat;
use App\Models\RoleStat;
use App\Models\SignupStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Shapes the stats collected by `nntmux:collect-stats` for the admin Site Stats page:
 * totals, shares and a 12-month signup series, so the view only has to render.
 */
final class SiteStatsReport
{
    /**
     * @return array{
     *     kpis: array{total_users: int, role_count: int, signups_this_month: int, signups_last_month: int, releases_last_week: int, top_category: ?string, top_grabber: ?array{username: string, grabs: int}},
     *     categories: list<array{label: string, parent: string, count: int, share: float}>,
     *     parents: list<array{label: string, count: int, share: float}>,
     *     signups: list<array{label: string, month: string, count: int, height: float}>,
     *     signups_total: int,
     *     roles: list<array{role: string, users: int, share: float}>,
     *     grabbers: list<array{rank: int, username: string, grabs: int, bar: float}>,
     *     downloads: list<array{searchname: string, guid: string, grabs: int, adddate: ?string}>,
     *     updated_at: ?CarbonImmutable,
     *     is_empty: bool
     * }
     */
    public function build(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $categories = $this->categories();
        $roles = $this->roles();
        $grabbers = $this->grabbers();
        $signups = $this->signupSeries($now);
        $signupsByMonth = SignupStat::query()->pluck('signups', 'sort_date')->mapWithKeys(
            static fn ($count, $date): array => [substr((string) $date, 0, 7) => (int) $count]
        );
        $downloads = $this->downloads();
        $totalUsers = array_sum(array_column($roles, 'users'));

        return [
            'kpis' => [
                'total_users' => $totalUsers,
                'role_count' => count($roles),
                'signups_this_month' => $signupsByMonth[$now->format('Y-m')] ?? 0,
                'signups_last_month' => $signupsByMonth[$now->subMonthNoOverflow()->format('Y-m')] ?? 0,
                'releases_last_week' => array_sum(array_column($categories, 'count')),
                'top_category' => $categories[0]['label'] ?? null,
                'top_grabber' => isset($grabbers[0]) ? ['username' => $grabbers[0]['username'], 'grabs' => $grabbers[0]['grabs']] : null,
            ],
            'categories' => $categories,
            'parents' => $this->parents($categories),
            'signups' => $signups,
            'signups_total' => (int) $signupsByMonth->sum(),
            'roles' => $roles,
            'grabbers' => $grabbers,
            'downloads' => $downloads,
            'updated_at' => $this->updatedAt(),
            'is_empty' => $categories === [] && $roles === [] && $grabbers === [] && $signupsByMonth->isEmpty() && $downloads === [],
        ];
    }

    /**
     * @return list<array{label: string, parent: string, count: int, share: float}>
     */
    private function categories(): array
    {
        $rows = ReleaseStat::getRecentlyAdded();
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['count'], $rows));

        return array_values(array_map(static fn (array $row): array => [
            'label' => (string) $row['category'],
            'parent' => trim(explode('>', (string) $row['category'], 2)[0]),
            'count' => (int) $row['count'],
            'share' => self::share((int) $row['count'], $total),
        ], $rows));
    }

    /**
     * @param  list<array{label: string, parent: string, count: int, share: float}>  $categories
     * @return list<array{label: string, count: int, share: float}>
     */
    private function parents(array $categories): array
    {
        $total = array_sum(array_column($categories, 'count'));

        return collect($categories)
            ->groupBy('parent')
            ->map(static fn (Collection $rows, string $parent): array => [
                'label' => $parent,
                'count' => (int) $rows->sum('count'),
                'share' => self::share((int) $rows->sum('count'), $total),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * The last 12 months (oldest first), with zero for months without signups.
     *
     * @return list<array{label: string, month: string, count: int, height: float}>
     */
    private function signupSeries(CarbonImmutable $now): array
    {
        $counts = SignupStat::query()
            ->where('sort_date', '>=', $now->startOfMonth()->subMonths(11)->toDateString())
            ->pluck('signups', 'sort_date')
            ->mapWithKeys(static fn ($count, $date): array => [substr((string) $date, 0, 7) => (int) $count]);
        $peak = max(1, (int) $counts->max());

        $series = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            $month = $now->startOfMonth()->subMonths($offset);
            $count = $counts[$month->format('Y-m')] ?? 0;
            $series[] = [
                'label' => $month->format('M'),
                'month' => $month->format('F Y'),
                'count' => $count,
                'height' => round($count / $peak * 100, 1),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{role: string, users: int, share: float}>
     */
    private function roles(): array
    {
        $rows = RoleStat::getUsersByRole();
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['users'], $rows));

        return array_values(array_map(static fn (array $row): array => [
            'role' => (string) $row['role'],
            'users' => (int) $row['users'],
            'share' => self::share((int) $row['users'], $total),
        ], $rows));
    }

    /**
     * Bar widths are relative to the top grabber.
     *
     * @return list<array{rank: int, username: string, grabs: int, bar: float}>
     */
    private function grabbers(): array
    {
        $rows = GrabStat::getTopGrabbers();
        $peak = max(1, (int) ($rows[0]['grabs'] ?? 0));

        return array_values(array_map(static fn (array $row, int $index): array => [
            'rank' => $index + 1,
            'username' => (string) $row['username'],
            'grabs' => (int) $row['grabs'],
            'bar' => self::share((int) $row['grabs'], $peak),
        ], $rows, array_keys($rows)));
    }

    /**
     * @return list<array{searchname: string, guid: string, grabs: int, adddate: ?string}>
     */
    private function downloads(): array
    {
        return array_values(array_map(static fn (array $row): array => [
            'searchname' => (string) $row['searchname'],
            'guid' => (string) $row['guid'],
            'grabs' => (int) $row['grabs'],
            'adddate' => isset($row['adddate']) ? (string) $row['adddate'] : null,
        ], DownloadStat::getTopDownloads()));
    }

    private function updatedAt(): ?CarbonImmutable
    {
        $latest = collect([GrabStat::class, ReleaseStat::class, SignupStat::class, RoleStat::class, DownloadStat::class])
            ->map(static fn (string $model): mixed => $model::query()->max('updated_at'))
            ->filter()
            ->max();

        return $latest ? CarbonImmutable::parse((string) $latest) : null;
    }

    private static function share(int $value, int $total): float
    {
        return $total > 0 ? round($value / $total * 100, 1) : 0.0;
    }
}
