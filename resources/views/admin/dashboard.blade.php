@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Welcome Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-2">Admin Dashboard</h2>
        <p class="text-gray-600">Welcome to the administration panel</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Releases -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Releases</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['releases'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-download text-2xl text-blue-600 dark:text-blue-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-green-600 dark:text-green-400">
                    <i class="fas fa-arrow-up"></i> {{ number_format($stats['releases_today'] ?? 0) }} today
                </span>
            </div>
        </div>

        <!-- Active Users -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Active Users</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['users'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-2xl text-green-600 dark:text-green-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-blue-600 dark:text-blue-400">
                    <i class="fas fa-user-plus"></i> {{ $stats['users_today'] ?? 0 }} registered today
                </span>
            </div>
        </div>

        <!-- Active Groups -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Active Groups</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['active_groups'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-layer-group text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ number_format($stats['groups'] ?? 0) }} total groups
                </span>
            </div>
        </div>

        <!-- Failed Releases -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Failed Releases</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['failed'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600 dark:text-red-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ url('/admin/failed-releases') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                    View failed releases <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- User Statistics Widget -->
    @if(isset($userStats))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6 flex items-center">
            <i class="fas fa-chart-line mr-2 text-blue-600 dark:text-blue-400"></i>
            User Statistics
        </h3>

        <!-- Summary Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900 dark:to-blue-800 rounded-lg p-4 text-center">
                <p class="text-sm text-blue-600 dark:text-blue-300 font-medium mb-1">Total Users</p>
                <p class="text-2xl font-bold text-blue-800 dark:text-blue-100">{{ number_format($userStats['summary']['total_users']) }}</p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900 dark:to-green-800 rounded-lg p-4 text-center">
                <p class="text-sm text-green-600 dark:text-green-300 font-medium mb-1">Downloads Today</p>
                <p class="text-2xl font-bold text-green-800 dark:text-green-100">{{ number_format($userStats['summary']['downloads_today']) }}</p>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900 dark:to-purple-800 rounded-lg p-4 text-center">
                <p class="text-sm text-purple-600 dark:text-purple-300 font-medium mb-1">Downloads (7d)</p>
                <p class="text-2xl font-bold text-purple-800 dark:text-purple-100">{{ number_format($userStats['summary']['downloads_week']) }}</p>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900 dark:to-orange-800 rounded-lg p-4 text-center">
                <p class="text-sm text-orange-600 dark:text-orange-300 font-medium mb-1">API Hits Today</p>
                <p class="text-2xl font-bold text-orange-800 dark:text-orange-100">{{ number_format($userStats['summary']['api_hits_today']) }}</p>
            </div>
            <div class="bg-gradient-to-br from-pink-50 to-pink-100 dark:from-pink-900 dark:to-pink-800 rounded-lg p-4 text-center">
                <p class="text-sm text-pink-600 dark:text-pink-300 font-medium mb-1">API Hits (7d)</p>
                <p class="text-2xl font-bold text-pink-800 dark:text-pink-100">{{ number_format($userStats['summary']['api_hits_week']) }}</p>
            </div>
        </div>

        <!-- Tables and Graphs Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Users by Role Table -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-user-tag mr-2 text-indigo-600 dark:text-indigo-400"></i>
                    Users by Role
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Count</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php
                                $totalUsers = collect($userStats['users_by_role'])->sum('count');
                            @endphp
                            @foreach($userStats['users_by_role'] as $roleData)
                            <tr class="hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ ucfirst($roleData['role']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ number_format($roleData['count']) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">
                                    {{ $totalUsers > 0 ? number_format(($roleData['count'] / $totalUsers) * 100, 1) : 0 }}%
                                </td>
                            </tr>
                            @endforeach
                            @if(empty($userStats['users_by_role']))
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">
                                    No data available
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Downloaders Table -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-trophy mr-2 text-yellow-600 dark:text-yellow-400"></i>
                    Top Downloaders (Last 7 Days)
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Username</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Downloads</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($userStats['top_downloaders'] as $index => $downloader)
                            <tr class="hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                    @if($index === 0)
                                        <i class="fas fa-medal text-yellow-500 text-lg"></i>
                                    @elseif($index === 1)
                                        <i class="fas fa-medal text-gray-400 text-lg"></i>
                                    @elseif($index === 2)
                                        <i class="fas fa-medal text-orange-600 text-lg"></i>
                                    @else
                                        <span class="text-gray-500">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $downloader->username }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ number_format($downloader->download_count) }}
                                </td>
                            </tr>
                            @endforeach
                            @if(empty($userStats['top_downloaders']))
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">
                                    No downloads in the last 7 days
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Downloads Chart -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-chart-bar mr-2 text-green-600 dark:text-green-400"></i>
                    Downloads (Last 7 Days)
                </h4>
                <div style="height: 250px; max-height: 250px; position: relative;">
                    <canvas id="downloadsChart"></canvas>
                </div>
            </div>

            <!-- API Hits Chart -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-chart-area mr-2 text-purple-600 dark:text-purple-400"></i>
                    API Hits (Last 7 Days)
                </h4>
                <div style="height: 250px; max-height: 250px; position: relative;">
                    <canvas id="apiHitsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- System Status -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">System Status</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Database</span>
                    <span class="px-3 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-sm">
                        <i class="fas fa-check-circle"></i> Connected
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Cache</span>
                    <span class="px-3 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-sm">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Queue</span>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                        <i class="fas fa-check-circle"></i> Running
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Disk Space</span>
                    <span class="text-sm text-gray-600">{{ $stats['disk_free'] ?? 'N/A' }} available</span>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                @if(isset($recent_activity) && count($recent_activity) > 0)
                    @foreach($recent_activity as $activity)
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-{{ $activity->icon ?? 'info' }} text-blue-600 dark:text-blue-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800">{{ $activity->message }}</p>
                                <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-gray-500 text-center py-4">No recent activity</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Quick Links</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ url('/admin/user-list') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition">
                <i class="fas fa-users text-3xl text-blue-600 dark:text-blue-400 mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Manage Users</span>
            </a>
            <a href="{{ url('/admin/release-list') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition">
                <i class="fas fa-download text-3xl text-green-600 mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Manage Releases</span>
            </a>
            <a href="{{ url('/admin/category-list') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition">
                <i class="fas fa-folder text-3xl text-purple-600 mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Categories</span>
            </a>
            <a href="{{ url('/admin/site-edit') }}" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition">
                <i class="fas fa-cog text-3xl text-orange-600 mb-2"></i>
                <span class="text-sm font-medium text-gray-700">Settings</span>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(isset($userStats))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for dark mode
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#e5e7eb' : '#374151';
    const gridColor = isDarkMode ? '#374151' : '#e5e7eb';

    // Downloads Chart
    const downloadsCtx = document.getElementById('downloadsChart');
    if (downloadsCtx) {
        const downloadsData = {!! json_encode($userStats['downloads_per_day']) !!};
        new Chart(downloadsCtx, {
            type: 'bar',
            data: {
                labels: downloadsData.map(d => d.date),
                datasets: [{
                    label: 'Downloads',
                    data: downloadsData.map(d => d.count),
                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: isDarkMode ? '#1f2937' : '#ffffff',
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: gridColor,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Downloads: ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor,
                            precision: 0
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // API Hits Chart
    const apiHitsCtx = document.getElementById('apiHitsChart');
    if (apiHitsCtx) {
        const apiHitsData = {!! json_encode($userStats['api_hits_per_day']) !!};
        new Chart(apiHitsCtx, {
            type: 'line',
            data: {
                labels: apiHitsData.map(d => d.date),
                datasets: [{
                    label: 'API Hits',
                    data: apiHitsData.map(d => d.count),
                    backgroundColor: 'rgba(147, 51, 234, 0.1)',
                    borderColor: 'rgba(147, 51, 234, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgba(147, 51, 234, 1)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: isDarkMode ? '#1f2937' : '#ffffff',
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: gridColor,
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'API Hits: ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor,
                            precision: 0
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        }
                    }
                }
            }
        });
    }
});
</script>
@endif
@endpush
