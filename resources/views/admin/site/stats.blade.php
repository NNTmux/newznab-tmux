@extends('layouts.admin')

@section('content')
@php
    $kpis = $report['kpis'];
    $signupDelta = $kpis['signups_this_month'] - $kpis['signups_last_month'];
    // Literal classes so Tailwind can see them; main categories cycle through this palette.
    $parentColors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500', 'bg-violet-500', 'bg-pink-500', 'bg-gray-400'];
    $panel = 'admin-card surface-panel rounded-2xl border p-5 shadow-sm';
    $panelTitle = 'flex items-center gap-2 text-base font-semibold text-fg';
    $barTrack = 'h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700';
@endphp
<div class="site-stats space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-chart-column" subtitle="Users, signups, release volume and downloads at a glance.">
        <x-slot:actions>
            <p class="text-sm text-fg-muted">
                <i aria-hidden="true" class="fas fa-rotate mr-1"></i>
                @if($report['updated_at'])
                    Updated {{ $report['updated_at']->diffForHumans() }}
                @else
                    Not collected yet
                @endif
            </p>
        </x-slot:actions>
    </x-admin.page-header>

    @if($report['is_empty'])
        <x-admin.card>
            <x-admin.empty-state icon="fas fa-chart-column" title="No statistics available" message="Statistics appear once the scheduled nntmux:collect-stats command has run." />
        </x-admin.card>
    @else
        {{-- Key numbers --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stat-card label="Users" :value="number_format($kpis['total_users'])" icon="fas fa-users" color="primary"
                               :footer="$kpis['role_count'].' '.Str::plural('role', $kpis['role_count'])" />
            <x-admin.stat-card label="New users this month" :value="number_format($kpis['signups_this_month'])" icon="fas fa-user-plus" color="success">
                <x-slot:footerSlot>
                    <p class="mt-4 text-sm {{ $signupDelta > 0 ? 'text-success-700 dark:text-success-300' : ($signupDelta < 0 ? 'text-danger-700 dark:text-danger-300' : 'text-fg-muted') }}">
                        <i aria-hidden="true" class="fas {{ $signupDelta > 0 ? 'fa-arrow-trend-up' : ($signupDelta < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }} mr-1"></i>
                        {{ $signupDelta > 0 ? '+' : '' }}{{ number_format($signupDelta) }} vs last month ({{ number_format($kpis['signups_last_month']) }})
                    </p>
                </x-slot:footerSlot>
            </x-admin.stat-card>
            <x-admin.stat-card label="Releases, last 7 days" :value="number_format($kpis['releases_last_week'])" icon="fas fa-layer-group" color="info"
                               :footer="$kpis['top_category'] ? 'Most active: '.$kpis['top_category'] : 'No new releases'" />
            <x-admin.stat-card label="Top grabber" :value="$kpis['top_grabber'] ? number_format($kpis['top_grabber']['grabs']) : '—'" icon="fas fa-trophy" color="warning"
                               :footer="$kpis['top_grabber'] ? $kpis['top_grabber']['username'].' · grabs' : 'No grabs yet'" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Releases by category --}}
            <section class="{{ $panel }} xl:col-span-2" aria-labelledby="stats-categories">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="stats-categories" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-folder-tree text-link dark:text-primary-400"></i> Releases added, last 7 days</h2>
                    <span class="text-sm text-fg-muted tabular-nums">{{ number_format($kpis['releases_last_week']) }} total</span>
                </div>

                @if($report['parents'] === [])
                    <p class="mt-4 text-sm text-fg-muted">No releases were added in the past week.</p>
                @else
                    {{-- Share per main category --}}
                    <div class="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="img"
                         aria-label="{{ collect($report['parents'])->map(fn ($p) => $p['label'].' '.$p['share'].'%')->implode(', ') }}">
                        @foreach($report['parents'] as $parent)
                            <span class="{{ $parentColors[$loop->index % count($parentColors)] }} h-full" style="width: {{ $parent['share'] }}%" title="{{ $parent['label'] }}: {{ number_format($parent['count']) }}"></span>
                        @endforeach
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-fg-muted">
                        @foreach($report['parents'] as $parent)
                            <li class="flex items-center gap-1.5">
                                <span aria-hidden="true" class="{{ $parentColors[$loop->index % count($parentColors)] }} h-2.5 w-2.5 rounded-full"></span>
                                <span class="font-medium text-fg">{{ $parent['label'] }}</span>
                                <span class="tabular-nums">{{ number_format($parent['count']) }} · {{ $parent['share'] }}%</span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Every subcategory, two columns on wide screens --}}
                    <ul class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2 text-sm lg:grid-cols-2" aria-label="Releases added in the last 7 days by category">
                        @foreach($report['categories'] as $category)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1">
                                <span class="truncate text-fg" title="{{ $category['label'] }}">{{ $category['label'] }}</span>
                                <span class="text-right font-semibold tabular-nums text-fg">{{ number_format($category['count']) }}<span class="sr-only"> releases, {{ $category['share'] }}%</span></span>
                                <span aria-hidden="true" class="col-span-2 {{ $barTrack }}"><span class="block h-full rounded-full bg-primary-500" style="width: {{ $category['share'] }}%"></span></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Users by role --}}
            <section class="{{ $panel }}" aria-labelledby="stats-roles">
                <h2 id="stats-roles" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-user-shield text-link dark:text-primary-400"></i> Users by role</h2>
                @if($report['roles'] === [])
                    <p class="mt-4 text-sm text-fg-muted">No role data yet.</p>
                @else
                    <ul class="mt-4 space-y-3 text-sm" aria-label="Users by role">
                        @foreach($report['roles'] as $role)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1">
                                <span class="truncate capitalize text-fg">{{ $role['role'] }}</span>
                                <span class="text-right tabular-nums text-fg"><span class="font-semibold">{{ number_format($role['users']) }}</span> <span class="text-xs text-fg-muted">{{ $role['share'] }}%</span></span>
                                <span aria-hidden="true" class="col-span-2 {{ $barTrack }}"><span class="block h-full rounded-full bg-violet-500" style="width: {{ $role['share'] }}%"></span></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Signups, last 12 months --}}
            <section class="{{ $panel }} xl:col-span-2" aria-labelledby="stats-signups">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="stats-signups" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-chart-column text-link dark:text-primary-400"></i> Signups, last 12 months</h2>
                    <span class="text-sm text-fg-muted tabular-nums">{{ number_format(array_sum(array_column($report['signups'], 'count'))) }} in 12 months · {{ number_format($report['signups_total']) }} all time</span>
                </div>
                <ol class="mt-5 grid h-44 grid-cols-12 items-end gap-1.5 sm:gap-2" aria-label="Signups per month">
                    @foreach($report['signups'] as $month)
                        <li class="flex h-full flex-col items-center justify-end gap-1" title="{{ $month['month'] }}: {{ number_format($month['count']) }}">
                            <span class="text-[0.65rem] font-semibold tabular-nums text-fg">{{ $month['count'] > 0 ? number_format($month['count']) : '' }}</span>
                            <span aria-hidden="true" @class(['w-full rounded-t-md', 'bg-primary-500' => $loop->last, 'bg-primary-300 dark:bg-primary-700' => ! $loop->last])
                                  style="height: {{ max($month['height'], $month['count'] > 0 ? 3 : 1) }}%"></span>
                            <span class="text-[0.65rem] text-fg-muted">{{ $month['label'] }}</span>
                            <span class="sr-only">{{ $month['month'] }}: {{ number_format($month['count']) }} signups</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Top grabbers --}}
            <section class="{{ $panel }}" aria-labelledby="stats-grabbers">
                <h2 id="stats-grabbers" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-trophy text-link dark:text-primary-400"></i> Top grabbers</h2>
                @if($report['grabbers'] === [])
                    <p class="mt-4 text-sm text-fg-muted">Nobody has downloaded anything yet.</p>
                @else
                    <ol class="mt-4 space-y-2.5 text-sm">
                        @foreach($report['grabbers'] as $grabber)
                            <li class="grid grid-cols-[1.5rem_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-1">
                                <span class="text-xs font-semibold tabular-nums text-fg-muted">{{ $grabber['rank'] }}</span>
                                <span class="truncate text-fg">{{ $grabber['username'] }}</span>
                                <span class="font-semibold tabular-nums text-fg">{{ number_format($grabber['grabs']) }}</span>
                                <span aria-hidden="true" class="col-start-2 col-end-4 {{ $barTrack }}"><span class="block h-full rounded-full bg-warning-500" style="width: {{ $grabber['bar'] }}%"></span></span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        {{-- Most downloaded releases --}}
        @if($report['downloads'] !== [])
            <section class="{{ $panel }}" aria-labelledby="stats-downloads">
                <h2 id="stats-downloads" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-download text-link dark:text-primary-400"></i> Most downloaded releases</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <x-admin.th width="10">#</x-admin.th>
                                <x-admin.th>Release</x-admin.th>
                                <x-admin.th>Added</x-admin.th>
                                <x-admin.th align="right">Grabs</x-admin.th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($report['downloads'] as $download)
                                <tr>
                                    <td class="px-4 py-2 tabular-nums text-fg-muted">{{ $loop->iteration }}</td>
                                    <td class="max-w-0 px-4 py-2">
                                        <a href="{{ url('/details/'.$download['guid']) }}" class="block truncate text-link hover:underline dark:text-primary-400" title="{{ $download['searchname'] }}">{{ $download['searchname'] }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-fg-muted">{{ $download['adddate'] ? userDateDiffForHumans($download['adddate']) : '—' }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums text-fg">{{ number_format($download['grabs']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
