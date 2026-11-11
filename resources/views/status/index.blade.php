@extends('layouts.main')

@section('content')
@php
    use App\Enums\ServiceStatusEnum;
    use App\Enums\IncidentImpactEnum;
    use App\Enums\IncidentStatusEnum;

    $overallBanner = match ($overallStatus) {
        ServiceStatusEnum::Operational => 'border-success-500/50 bg-success-600 text-white dark:bg-success-900 dark:text-success-200',
        ServiceStatusEnum::Degraded => 'border-warning-500/50 bg-warning-500 text-warning-950 dark:bg-warning-900 dark:text-warning-200',
        ServiceStatusEnum::Maintenance => 'border-primary-500/50 bg-accent text-white dark:bg-primary-900 dark:text-primary-200',
        ServiceStatusEnum::PartialOutage => 'border-orange-500/50 bg-orange-600 text-white dark:bg-orange-900 dark:text-orange-200',
        ServiceStatusEnum::MajorOutage => 'border-danger-500/50 bg-danger-600 text-white dark:bg-danger-900 dark:text-danger-200',
    };

    $serviceBadge = static function (ServiceStatusEnum $s): string {
        $base = 'px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$s->badgeClasses();
    };

    $impactBadge = static function (IncidentImpactEnum $i): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$i->badgeClasses();
    };

    $incidentStatusBadge = static function (IncidentStatusEnum $st): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';

        return $base.' '.$st->badgeClasses();
    };
@endphp

<div class="w-full max-w-5xl mx-auto">
    <x-page-header title="Service status" eyebrow="Live system health" description="Current availability, performance, and incident updates for core services." icon="fas fa-heart-pulse">
        <x-slot:stats>
            <span class="workspace-hero__stat"><i class="fas fa-signal" aria-hidden="true"></i>{{ $overallStatus->label() }}</span>
            <span class="workspace-hero__stat"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i>{{ number_format($activeIncidents->count()) }} active {{ Str::plural('incident', $activeIncidents->count()) }}</span>
        </x-slot:stats>
    </x-page-header>

    <div class="surface-panel rounded-xl shadow-sm mb-6 overflow-hidden border {{ $overallBanner }}">
        <div class="px-6 py-5">
            <h2 class="text-2xl font-bold tracking-tight">Current status</h2>
            <p class="mt-2 text-sm leading-relaxed">
                Overall: <strong class="font-semibold">{{ $overallStatus->label() }}</strong>
                @if($activeIncidents->isNotEmpty())
                    &mdash; {{ $activeIncidents->count() }} active {{ Str::plural('incident', $activeIncidents->count()) }}
                @else
                    &mdash; No active incidents
                @endif
            </p>
        </div>
    </div>

    <div class="surface-panel rounded-xl shadow-sm mb-6">
        <div class="surface-panel-alt px-6 py-4 border-b rounded-t-lg">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Services</h2>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">Uptime and current health for core endpoints.</p>
        </div>
        <div class="px-6 py-6 space-y-4">
            @forelse($services as $service)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-white/50 dark:bg-gray-800/90">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $service->name }}</div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                            Uptime (reported): {{ number_format((float) $service->uptime_percentage, 2) }}%
                            @if($service->response_time_ms !== null)
                                &middot; Response ~{{ $service->response_time_ms }} ms
                            @endif
                            @if($service->last_checked_at)
                                &middot; Checked {{ $service->last_checked_at->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                    <span class="{{ $serviceBadge($service->status) }}">
                        {{ $service->status->label() }}
                    </span>
                </div>
            @empty
                <p class="text-gray-700 dark:text-gray-300">No services are configured for display.</p>
            @endforelse
        </div>
    </div>

    <div class="surface-panel rounded-xl shadow-sm mb-6">
        <div class="surface-panel-alt px-6 py-4 border-b rounded-t-lg">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Active incidents</h2>
        </div>
        <div class="px-6 py-6 space-y-4">
            @forelse($activeIncidents as $incident)
                <article class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white/30 dark:bg-gray-900/20">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $incident->title }}</h3>
                        <span class="{{ $impactBadge($incident->impact) }}">
                            {{ $incident->impact->label() }} impact
                        </span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-2 whitespace-pre-wrap">{{ $incident->description }}</p>
                    <div class="mt-3 text-xs text-gray-600 dark:text-gray-400 flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span class="inline-flex items-center gap-1">
                            <i aria-hidden="true" class="fas fa-layer-group"></i>
                            <span>{{ $incident->services->pluck('name')->join(', ') ?: '—' }}</span>
                        </span>
                        <span class="{{ $incidentStatusBadge($incident->status) }}">
                            <i aria-hidden="true" class="fas fa-flag text-[10px] opacity-90"></i>{{ $incident->status->label() }}
                        </span>
                        <span><i aria-hidden="true" class="fas fa-clock mr-1"></i>Started {{ $incident->started_at->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}</span>
                    </div>
                </article>
            @empty
                <p class="text-gray-700 dark:text-gray-300">There are no active incidents.</p>
            @endforelse
        </div>
    </div>

    <div class="surface-panel rounded-xl shadow-sm mb-6">
        <div class="surface-panel-alt px-6 py-4 border-b rounded-t-lg">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent resolved incidents</h2>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">Last 30 days</p>
        </div>
        <div class="px-6 py-6 space-y-6">
            @forelse($recentResolvedGrouped as $date => $group)
                <div>
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ $date === 'unknown' ? 'Unknown date' : \Carbon\Carbon::parse($date)->format('F j, Y') }}</h3>
                    <ul class="space-y-3">
                        @foreach($group as $incident)
                            <li class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $incident->title }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 flex flex-wrap gap-x-2 gap-y-1 items-center">
                                    <span class="{{ $impactBadge($incident->impact) }}">{{ $incident->impact->label() }}</span>
                                    <span>{{ $incident->services->pluck('name')->join(', ') }} &middot; Resolved {{ $incident->resolved_at?->timezone(config('app.timezone'))->format('M j, g:i A') }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-gray-700 dark:text-gray-300">No resolved incidents in the last 30 days.</p>
            @endforelse
        </div>
    </div>

</div>
@endsection
