@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header title="Promotion Statistics" icon="fas fa-chart-bar" subtitle="Overview of all promotion activities">
        <x-slot:actions>
            <x-admin.button :href="route('admin.promotions.index')" tone="gray" icon="fas fa-arrow-left">Back to Promotions</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <!-- Date Range Filter -->
    <x-admin.card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.promotions.statistics') }}" class="flex flex-wrap gap-4 items-end" x-data="periodFilter({{ $selectedPeriod === 'custom' ? 'true' : 'false' }})" x-ref="periodForm">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quick Select</label>
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

    <!-- Overall Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-primary-100 dark:bg-primary-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-gift text-2xl text-link dark:text-primary-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Promotions</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $overallStats['total_promotions'] }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-success-100 dark:bg-success-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-check-circle text-2xl text-success-600 dark:text-success-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active Promotions</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ $overallStats['active_promotions'] }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-purple-100 dark:bg-purple-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-arrow-up text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Applications</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['total_applications']) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-warning-100 dark:bg-warning-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-users text-2xl text-warning-600 dark:text-warning-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unique Users</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['unique_users']) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card class="p-6">
            <div class="flex items-center">
                <div class="shrink-0 bg-danger-100 dark:bg-danger-900 rounded-full p-3">
                    <i aria-hidden="true" class="fas fa-calendar-plus text-2xl text-danger-600 dark:text-danger-400"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Days Added</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($overallStats['total_days_added']) }}</p>
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Promotions -->
        <x-admin.card>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i aria-hidden="true" class="fas fa-trophy mr-2 text-amber-500"></i>Top Promotions by Usage
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
                                        <span><i aria-hidden="true" class="fas fa-arrow-up mr-1"></i>{{ $promotion->statistics_count }} applications</span>
                                        <span><i aria-hidden="true" class="fas fa-calendar mr-1"></i>{{ $promotion->additional_days }} days</span>
                                    </div>
                                </div>
                                <a href="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="px-3 py-1 bg-accent text-white text-sm rounded hover:bg-accent-hover">
                                    View Details
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-admin.card>

        <!-- Statistics by Role -->
        <x-admin.card>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i aria-hidden="true" class="fas fa-user-tag mr-2"></i>Statistics by Role
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
        </x-admin.card>
    </div>

    <!-- All Promotions with Statistics -->
    <x-admin.card>
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-list mr-2"></i>All Promotions Statistics
            </h2>
        </div>
        <x-admin.data-table>
            <x-slot:head>
                        <x-admin.th>Promotion</x-admin.th>
                        <x-admin.th>Applications</x-admin.th>
                        <x-admin.th>Days/Application</x-admin.th>
                        <x-admin.th>Status</x-admin.th>
                        <x-admin.th>Actions</x-admin.th>
            </x-slot:head>

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
                                    <x-admin.badge tone="success" icon="fas fa-check">Active</x-admin.badge>
                                @else
                                    <x-admin.badge tone="neutral">Inactive</x-admin.badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.promotions.show-statistics', $promotion->id) }}" class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">
                                    View Details <i aria-hidden="true" class="fas fa-arrow-right ml-1"></i>
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
        </x-admin.data-table>
    </x-admin.card>

    <!-- Recent Activity -->
    <x-admin.card class="mt-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                <i aria-hidden="true" class="fas fa-clock mr-2"></i>Recent Promotion Activity
            </h2>
        </div>
        <x-admin.data-table>
            <x-slot:head>
                        <x-admin.th>User</x-admin.th>
                        <x-admin.th>Promotion</x-admin.th>
                        <x-admin.th>Role</x-admin.th>
                        <x-admin.th>Days Added</x-admin.th>
                        <x-admin.th>Applied At</x-admin.th>
            </x-slot:head>

                    @forelse($recentActivity as $activity)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $activity->user->username ?? 'Unknown User' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                {{ $activity->promotion->name ?? 'Unknown Promotion' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <span class="px-2 py-1 bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 rounded text-xs">
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
        </x-admin.data-table>
    </x-admin.card>
</div>
@endsection
