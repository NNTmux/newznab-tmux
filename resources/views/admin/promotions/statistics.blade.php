@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-chart-bar mr-2"></i>Promotion Statistics
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Overview of all promotion activities</p>
            </div>
            <a href="{{ route('admin.promotions.index') }}" class="px-4 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700">
                <i class="fa fa-arrow-left mr-2"></i>Back to Promotions
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-6">
        <form method="GET" action="{{ route('admin.promotions.statistics') }}" class="flex flex-wrap gap-4 items-end" x-data="periodFilter({{ $selectedPeriod === 'custom' ? 'true' : 'false' }})" x-ref="periodForm">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quick Select</label>
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

    <!-- Overall Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                    <i class="fa fa-gift text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Promotions</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $overallStats['total_promotions'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-green-100 dark:bg-green-900 rounded-full p-3">
                    <i class="fa fa-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active Promotions</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $overallStats['active_promotions'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                    <i class="fa fa-arrow-up text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Applications</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['total_applications']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-full p-3">
                    <i class="fa fa-users text-2xl text-yellow-600 dark:text-yellow-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unique Users</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['unique_users']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-red-100 dark:bg-red-900 rounded-full p-3">
                    <i class="fa fa-calendar-plus text-2xl text-red-600 dark:text-red-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Days Added</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['total_days_added']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Promotions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-trophy mr-2 text-yellow-500"></i>Top Promotions by Usage
                </h2>
            </div>
            <div class="p-6">
                @if($topPromotions->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No promotion data available</p>
                @else
                    <div class="space-y-4">
                        @foreach($topPromotions as $promotion)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ $promotion->name }}</h3>
                                    <div class="flex gap-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span><i class="fa fa-arrow-up mr-1"></i>{{ $promotion->statistics_count }} applications</span>
                                        <span><i class="fa fa-calendar mr-1"></i>{{ $promotion->additional_days }} days</span>
                                    </div>
                                </div>
                                <a href="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    View Details
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Statistics by Role -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-user-tag mr-2"></i>Statistics by Role
                </h2>
            </div>
            <div class="p-6">
                @if($statsByRole->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No role data available</p>
                @else
                    <div class="space-y-4">
                        @foreach($statsByRole as $roleStat)
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">
                                    {{ $roleStat->role->name ?? 'Unknown Role' }}
                                </h3>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Upgrades</p>
                                        <p class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($roleStat->total_upgrades) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Users</p>
                                        <p class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($roleStat->unique_users) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400">Days Added</p>
                                        <p class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($roleStat->total_days) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- All Promotions with Statistics -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-list mr-2"></i>All Promotions Statistics
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Promotion</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applications</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Days/Application</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($promotions as $promotion)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $promotion->name }}</div>
                                @if($promotion->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($promotion->description, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ number_format($promotion->statistics_count) }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">times</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $promotion->additional_days }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">days</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($promotion->is_active && $promotion->isCurrentlyActive())
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                        <i class="fa fa-check mr-1"></i>Active
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                    View Details <i class="fa fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No promotions available
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mt-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-clock mr-2"></i>Recent Promotion Activity
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Promotion</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Days Added</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Applied At</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($recentActivity as $activity)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $activity->user->username ?? 'Unknown User' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                {{ $activity->promotion->name ?? 'Unknown Promotion' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                                    {{ $activity->role->name ?? 'Unknown Role' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                +{{ $activity->days_added }} days
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $activity->applied_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No recent activity
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

