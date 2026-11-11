@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$promotion->name.' - Statistics'" icon="fas fa-chart-line" subtitle="Detailed statistics for this promotion">
        <x-slot:actions>
            <x-admin.button :href="route('admin.promotions.statistics')" tone="gray" icon="fas fa-chart-bar">All Statistics</x-admin.button>
            <x-admin.button :href="route('admin.promotions.index')" tone="gray" icon="fas fa-arrow-left">Back to Promotions</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <!-- Promotion Info Card -->
    <x-admin.card class="p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                @if($promotion->is_active && $promotion->isCurrentlyActive())
                    <x-admin.badge tone="success" icon="fas fa-check-circle">Active</x-admin.badge>
                @else
                    <x-admin.badge tone="neutral" icon="fas fa-pause-circle">Inactive</x-admin.badge>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Additional Days</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->additional_days }} days</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Start Date</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->start_date ? formatDate($promotion->start_date) : 'No limit' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">End Date</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->end_date ? formatDate($promotion->end_date) : 'No limit' }}</p>
            </div>
        </div>
        @if($promotion->description)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Description</p>
                <p class="text-gray-800 dark:text-gray-200 mt-1">{{ $promotion->description }}</p>
            </div>
        @endif
    </x-admin.card>

    <!-- Date Range Filter -->
    <x-admin.card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="flex flex-wrap gap-4 items-end" x-data="periodFilter({{ $selectedPeriod === 'custom' ? 'true' : 'false' }})" x-ref="periodForm">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time Period</label>
                <select name="period" class="form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" @change="onPeriodChange()">
                    <option value="7days" {{ $selectedPeriod === '7days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="30days" {{ $selectedPeriod === '30days' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="90days" {{ $selectedPeriod === '90days' ? 'selected' : '' }}>Last 90 Days</option>
                    <option value="year" {{ $selectedPeriod === 'year' ? 'selected' : '' }}>Last Year</option>
                    <option value="all" {{ $selectedPeriod === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="custom" {{ $selectedPeriod === 'custom' ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>
            <div x-show="showCustom" x-cloak class="flex gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate ? formatDate($startDate) : '' }}" class="form-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                    <input type="date" name="end_date" value="{{ formatDate($endDate) }}" class="form-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover">
                        <i aria-hidden="true" class="fas fa-filter mr-2"></i>Apply
                    </button>
                </div>
            </div>
        </form>
    </x-admin.card>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-primary-100 dark:bg-primary-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-arrow-up text-2xl text-link dark:text-primary-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Upgrades</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['total_upgrades']) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-success-100 dark:bg-success-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-users text-2xl text-success-600 dark:text-success-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unique Users</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['unique_users']) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-calendar-plus text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Days Added</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['total_days_added']) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-warning-100 dark:bg-warning-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-user-tag text-2xl text-warning-600 dark:text-warning-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Roles Affected</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['roles_affected']) }}</p>
                </div>
            </div>
        </x-admin.card>
    </div>

    <!-- Statistics by Role -->
    @if(count($statsByRole) > 0)
    <x-admin.card class="mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-user-tag mr-2"></i>Statistics by Role
            </h2>
        </div>
        <x-admin.data-table>
            <x-slot:head>
                        <x-admin.th>Role</x-admin.th>
                        <x-admin.th>Total Upgrades</x-admin.th>
                        <x-admin.th>Unique Users</x-admin.th>
                        <x-admin.th>Total Days Added</x-admin.th>
            </x-slot:head>

                    @foreach($statsByRole as $roleStat)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 rounded font-medium">
                                    {{ $roleStat['role_name'] ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-gray-800 dark:text-gray-200">
                                {{ number_format($roleStat['total_upgrades']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-gray-800 dark:text-gray-200">
                                {{ number_format($roleStat['unique_users']) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-gray-800 dark:text-gray-200">
                                {{ number_format($roleStat['total_days_added']) }}
                            </td>
                        </tr>
                    @endforeach
        </x-admin.data-table>
    </x-admin.card>
    @endif

    <!-- Daily Activity Chart -->
    @if($dailyStats->isNotEmpty())
    <x-admin.card class="mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-chart-area mr-2"></i>Daily Activity
            </h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <x-admin.th>Date</x-admin.th>
                            <x-admin.th align="right">Applications</x-admin.th>
                            <x-admin.th align="right">Days Added</x-admin.th>
                            <x-admin.th></x-admin.th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $maxCount = $dailyStats->max('count');
                        @endphp
                        @foreach($dailyStats as $dayStat)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="py-2 text-gray-800 dark:text-gray-200">{{ \Carbon\Carbon::parse($dayStat->date)->format('M d, Y') }}</td>
                                <td class="text-right font-bold text-gray-800 dark:text-gray-200">{{ $dayStat->count }}</td>
                                <td class="text-right font-bold text-gray-800 dark:text-gray-200">{{ $dayStat->days }}</td>
                                <td class="pl-4">
                                    <div class="bg-primary-200 dark:bg-accent h-4 rounded" style="width: {{ ($dayStat->count / $maxCount) * 100 }}%"></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-admin.card>
    @endif

    <!-- Recent Applications -->
    <x-admin.card>
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-list mr-2"></i>Recent Applications
            </h2>
        </div>
        <x-admin.data-table>
            <x-slot:head>
                        <x-admin.th>User</x-admin.th>
                        <x-admin.th>Role</x-admin.th>
                        <x-admin.th>Days Added</x-admin.th>
                        <x-admin.th>Previous Expiry</x-admin.th>
                        <x-admin.th>New Expiry</x-admin.th>
                        <x-admin.th>Applied At</x-admin.th>
            </x-slot:head>

                    @forelse($applications as $application)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $application->user->username ?? 'Unknown User' }}
                                </div>
                                @if($application->user)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ID: {{ $application->user->id }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 rounded text-xs font-medium">
                                    {{ $application->role->name ?? 'Unknown Role' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-lg font-bold text-success-600 dark:text-success-400">+{{ $application->days_added }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">days</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $application->previous_expiry_date ? formatDateTime($application->previous_expiry_date) : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $application->new_expiry_date ? formatDateTime($application->new_expiry_date) : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ formatDateTime($application->applied_at) }}</div>
                                <div class="text-xs">{{ $application->applied_at->diffForHumans() }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No applications found for this promotion
                            </td>
                        </tr>
                    @endforelse
        </x-admin.data-table>
        <x-admin.pagination :paginator="$applications->appends(request()->query())" />
    </x-admin.card>
</div>
@endsection
