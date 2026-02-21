@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-chart-line mr-2"></i>{{ $promotion->name }} - Statistics
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Detailed statistics for this promotion</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.promotions.statistics') }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700">
                    <i class="fa fa-chart-bar mr-2"></i>All Statistics
                </a>
                <a href="{{ route('admin.promotions.index') }}" class="px-4 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Promotions
                </a>
            </div>
        </div>
    </div>

    <!-- Promotion Info Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                @if($promotion->is_active && $promotion->isCurrentlyActive())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                        <i class="fa fa-check-circle mr-2"></i>Active
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                        <i class="fa fa-pause-circle mr-2"></i>Inactive
                    </span>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Additional Days</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->additional_days }} days</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Start Date</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->start_date ? $promotion->start_date->format('Y-m-d') : 'No limit' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">End Date</p>
                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">{{ $promotion->end_date ? $promotion->end_date->format('Y-m-d') : 'No limit' }}</p>
            </div>
        </div>
        @if($promotion->description)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Description</p>
                <p class="text-gray-800 dark:text-gray-200 mt-1">{{ $promotion->description }}</p>
            </div>
        @endif
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
        <form method="GET" action="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="flex flex-wrap gap-4 items-end" x-data="periodFilter({{ $selectedPeriod === 'custom' ? 'true' : 'false' }})" x-ref="periodForm">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time Period</label>
                <select name="period" class="form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" x-on:change="onPeriodChange()">
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
                    <input type="date" name="start_date" value="{{ $startDate ? $startDate->format('Y-m-d') : '' }}" class="form-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="form-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-filter mr-2"></i>Apply
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                    <i class="fa fa-arrow-up text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Upgrades</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['total_upgrades']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-full p-3">
                    <i class="fa fa-users text-2xl text-green-600 dark:text-green-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unique Users</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['unique_users']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                    <i class="fa fa-calendar-plus text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Days Added</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['total_days_added']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-full p-3">
                    <i class="fa fa-user-tag text-2xl text-yellow-600 dark:text-yellow-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Roles Affected</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['roles_affected']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics by Role -->
    @if(count($statsByRole) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-user-tag mr-2"></i>Statistics by Role
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Upgrades</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unique Users</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Days Added</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($statsByRole as $roleStat)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded font-medium">
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
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Daily Activity Chart -->
    @if($dailyStats->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-chart-area mr-2"></i>Daily Activity
            </h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 text-gray-600 dark:text-gray-400">Date</th>
                            <th class="text-right py-2 text-gray-600 dark:text-gray-400">Applications</th>
                            <th class="text-right py-2 text-gray-600 dark:text-gray-400">Days Added</th>
                            <th class="py-2"></th>
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
                                    <div class="bg-blue-200 dark:bg-blue-600 h-4 rounded" style="width: {{ ($dayStat->count / $maxCount) * 100 }}%"></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Applications -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-list mr-2"></i>Recent Applications
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Days Added</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Previous Expiry</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">New Expiry</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applied At</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
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
                                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs font-medium">
                                    {{ $application->role->name ?? 'Unknown Role' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-lg font-bold text-green-600 dark:text-green-400">+{{ $application->days_added }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">days</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $application->previous_expiry_date ? $application->previous_expiry_date->format('Y-m-d H:i') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $application->new_expiry_date ? $application->new_expiry_date->format('Y-m-d H:i') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $application->applied_at->format('Y-m-d H:i') }}</div>
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
                </tbody>
            </table>
        </div>
        @if($applications->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $applications->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

