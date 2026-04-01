@extends('layouts.main')

@section('content')
@php
    use App\Enums\ServiceStatusEnum;
    use App\Enums\IncidentImpactEnum;
    use App\Enums\IncidentStatusEnum;

    $overallBanner = match ($overallStatus) {
        ServiceStatusEnum::Operational => 'border-green-500/50 bg-green-600 text-white dark:bg-green-900 dark:text-green-200',
        ServiceStatusEnum::Degraded => 'border-yellow-500/50 bg-yellow-500 text-yellow-950 dark:bg-yellow-900 dark:text-yellow-200',
        ServiceStatusEnum::Maintenance => 'border-blue-500/50 bg-blue-600 text-white dark:bg-blue-900 dark:text-blue-200',
        ServiceStatusEnum::PartialOutage => 'border-orange-500/50 bg-orange-600 text-white dark:bg-orange-900 dark:text-orange-200',
        ServiceStatusEnum::MajorOutage => 'border-red-500/50 bg-red-600 text-white dark:bg-red-900 dark:text-red-200',
    };

    $serviceBadge = static function (ServiceStatusEnum $s): string {
        $base = 'px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($s) {
            ServiceStatusEnum::Operational => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
            ServiceStatusEnum::Degraded => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
            ServiceStatusEnum::Maintenance => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
            ServiceStatusEnum::PartialOutage => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            ServiceStatusEnum::MajorOutage => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
        };
    };

    $impactBadge = static function (IncidentImpactEnum $i): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($i) {
            IncidentImpactEnum::None => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
            IncidentImpactEnum::Minor => 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
            IncidentImpactEnum::Major => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            IncidentImpactEnum::Critical => 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
        };
    };

    $incidentStatusBadge = static function (IncidentStatusEnum $st): string {
        $base = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full';
        return $base.' '.match ($st) {
            IncidentStatusEnum::Investigating => 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
            IncidentStatusEnum::Identified => 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
            IncidentStatusEnum::Monitoring => 'bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200',
            IncidentStatusEnum::Resolved => 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
        };
    };
@endphp

<div class="w-full max-w-5xl mx-auto">
    <div class="surface-panel rounded-xl shadow-sm mb-6 overflow-hidden border {{ $overallBanner }}">
        <div class="px-6 py-5">
            <h1 class="text-2xl font-bold tracking-tight">Service status</h1>
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
                            <i class="fas fa-layer-group"></i>
                            <span>{{ $incident->services->pluck('name')->join(', ') ?: '—' }}</span>
                        </span>
                        <span class="{{ $incidentStatusBadge($incident->status) }}">
                            <i class="fas fa-flag text-[10px] opacity-90"></i>{{ $incident->status->label() }}
                        </span>
                        <span><i class="fas fa-clock mr-1"></i>Started {{ $incident->started_at->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}</span>
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

    <nav class="text-sm text-gray-700 dark:text-gray-300" aria-label="breadcrumb">
        <ol class="flex flex-wrap gap-2">
            <li><a href="{{ url($site['home_link'] ?? '/') }}" class="text-primary-600 dark:text-primary-400 hover:underline">Home</a></li>
            <li aria-hidden="true">/</li>
            <li class="text-gray-800 dark:text-gray-200">Status</li>
        </ol>
    </nav>
</div>
@endsection
