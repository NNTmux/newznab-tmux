@extends('layouts.admin')

@section('content')
<div x-data="adminDashboard" class="space-y-6" id="adminDashboard">
    <!-- Welcome Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-2">Admin Dashboard</h2>
        <p class="text-gray-600">Welcome to the administration panel</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <!-- Total Releases -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
                <a href="{{ url('/admin/failrel-list') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                    View failed releases <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Reported Releases -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Reported Releases</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['reported'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-flag text-2xl text-orange-600 dark:text-orange-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ url('/admin/release-reports') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                    View reports <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- User Statistics Widget -->
    @if(isset($userStats))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
                                    {{ $downloader['username'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ number_format($downloader['download_count']) }}
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
                    Downloads (Last 7 Days - Hourly)
                </h4>
                <div class="chart-container">
                    <canvas id="downloadsChart" data-chart-data="{{ json_encode($userStats['downloads_per_hour']) }}"></canvas>
                </div>
            </div>

            <!-- API Hits Chart -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-chart-area mr-2 text-purple-600 dark:text-purple-400"></i>
                    API Hits (Last 7 Days - Hourly)
                </h4>
                <div class="chart-container">
                    <canvas id="apiHitsChart" data-chart-data="{{ json_encode($userStats['api_hits_per_hour']) }}"></canvas>
                </div>
            </div>

            <!-- Downloads Per Minute Chart -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-green-600 dark:text-green-400"></i>
                    Downloads (Last 60 Minutes)
                </h4>
                <div class="chart-container">
                    <canvas id="downloadsMinuteChart" data-chart-data="{{ json_encode($userStats['downloads_per_minute']) }}"></canvas>
                </div>
            </div>

            <!-- API Hits Per Minute Chart -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-purple-600 dark:text-purple-400"></i>
                    API Hits (Last 60 Minutes)
                </h4>
                <div class="chart-container">
                    <canvas id="apiHitsMinuteChart" data-chart-data="{{ json_encode($userStats['api_hits_per_minute']) }}"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- System Metrics (CPU & RAM) -->
    @if(isset($systemMetrics))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6 flex items-center">
            <i class="fas fa-server mr-2 text-orange-600 dark:text-orange-400"></i>
            System Resources
        </h3>

        <!-- Current Usage Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900 dark:to-orange-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="text-sm text-orange-600 dark:text-orange-300 font-medium mb-1">CPU Usage</p>
                        <p class="text-3xl font-bold text-orange-800 dark:text-orange-100" data-metric="cpu-current">{{ number_format($systemMetrics['cpu']['current'], 1) }}%</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-200 dark:bg-orange-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-microchip text-2xl text-orange-600 dark:text-orange-300"></i>
                    </div>
                </div>
                <div class="border-t border-orange-200 dark:border-orange-700 pt-3 space-y-2">
                    <div class="flex justify-between text-xs">
                        <span class="text-orange-600 dark:text-orange-300">Cores:</span>
                        <span class="font-semibold text-orange-800 dark:text-orange-100">{{ $systemMetrics['cpu']['cores'] }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-orange-600 dark:text-orange-300">Threads:</span>
                        <span class="font-semibold text-orange-800 dark:text-orange-100">{{ $systemMetrics['cpu']['threads'] }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-orange-600 dark:text-orange-300">Load (1m):</span>
                        <span class="font-semibold text-orange-800 dark:text-orange-100" data-metric="cpu-load-1min">{{ $systemMetrics['cpu']['load_average']['1min'] }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-orange-600 dark:text-orange-300">Load (5m):</span>
                        <span class="font-semibold text-orange-800 dark:text-orange-100" data-metric="cpu-load-5min">{{ $systemMetrics['cpu']['load_average']['5min'] }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-orange-600 dark:text-orange-300">Load (15m):</span>
                        <span class="font-semibold text-orange-800 dark:text-orange-100" data-metric="cpu-load-15min">{{ $systemMetrics['cpu']['load_average']['15min'] }}</span>
                    </div>
                    @if($systemMetrics['cpu']['model'] !== 'Unknown')
                    <div class="pt-2 border-t border-orange-200 dark:border-orange-700">
                        <p class="text-xs text-orange-600 dark:text-orange-300 truncate" title="{{ $systemMetrics['cpu']['model'] }}">
                            <i class="fas fa-info-circle mr-1"></i>{{ $systemMetrics['cpu']['model'] }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-gradient-to-br from-cyan-50 to-cyan-100 dark:from-cyan-900 dark:to-cyan-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-cyan-600 dark:text-cyan-300 font-medium mb-1">RAM Usage</p>
                        <p class="text-3xl font-bold text-cyan-800 dark:text-cyan-100" data-metric="ram-current">{{ number_format($systemMetrics['ram']['percentage'], 1) }}%</p>
                        <p class="text-xs text-cyan-600 dark:text-cyan-300 mt-1" data-metric="ram-details">
                            {{ number_format($systemMetrics['ram']['used'], 2) }} GB / {{ number_format($systemMetrics['ram']['total'], 2) }} GB
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-cyan-200 dark:bg-cyan-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-memory text-2xl text-cyan-600 dark:text-cyan-300"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Last Updated Indicator -->
        <div class="mb-4 text-right">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                <i class="fas fa-sync-alt mr-1"></i>Last updated: <span data-metric="last-updated">{{ now()->format('H:i:s') }}</span>
                <span class="ml-2 text-green-600 dark:text-green-400">(Auto-updates every minute)</span>
            </span>
        </div>

        <!-- Historical Graphs -->
        <div class="space-y-6">
            <!-- 24 Hour Charts -->
            <div>
                <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                    <i class="fas fa-clock mr-2 text-blue-600 dark:text-blue-400"></i>
                    Last 24 Hours (Hour by Hour)
                </h4>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- CPU Usage 24h Graph -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-microchip mr-2 text-orange-600 dark:text-orange-400"></i>
                            CPU Usage
                        </h5>
                        <div class="chart-container">
                            <canvas id="cpuHistory24hChart"
                                    data-history="{{ json_encode($systemMetrics['cpu']['history_24h']) }}"
                                    data-chart-label="CPU Usage %"></canvas>
                        </div>
                    </div>

                    <!-- RAM Usage 24h Graph -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-memory mr-2 text-cyan-600 dark:text-cyan-400"></i>
                            RAM Usage
                        </h5>
                        <div class="chart-container">
                            <canvas id="ramHistory24hChart"
                                    data-history="{{ json_encode($systemMetrics['ram']['history_24h']) }}"
                                    data-chart-label="RAM Usage %"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 30 Day Charts -->
            <div>
                <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-purple-600 dark:text-purple-400"></i>
                    Last 30 Days (Day by Day)
                </h4>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- CPU Usage 30d Graph -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-microchip mr-2 text-orange-600 dark:text-orange-400"></i>
                            CPU Usage
                        </h5>
                        <div class="chart-container">
                            <canvas id="cpuHistory30dChart"
                                    data-history="{{ json_encode($systemMetrics['cpu']['history_30d']) }}"
                                    data-chart-label="CPU Usage %"></canvas>
                        </div>
                    </div>

                    <!-- RAM Usage 30d Graph -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-memory mr-2 text-cyan-600 dark:text-cyan-400"></i>
                            RAM Usage
                        </h5>
                        <div class="chart-container">
                            <canvas id="ramHistory30dChart"
                                    data-history="{{ json_encode($systemMetrics['ram']['history_30d']) }}"
                                    data-chart-label="RAM Usage %"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- System Status -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
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
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center justify-between">
                <span>
                    <i class="fas fa-history mr-2 text-indigo-600 dark:text-indigo-400"></i>
                    Recent User Activity
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400" id="activity-last-updated">
                    <i class="fas fa-sync-alt"></i> Auto-refreshes every 20 minutes
                </span>
            </h3>
            <div x-data="recentActivity" class="space-y-3" id="recent-activity-container" data-refresh-url="{{ route('admin.api.user-activity.recent') }}">
                @if(isset($recent_activity) && count($recent_activity) > 0)
                    @foreach($recent_activity as $activity)
                        <div class="flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                            <div class="w-8 h-8 {{ $activity->icon_bg }} rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-{{ $activity->icon }} {{ $activity->icon_color }} text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800 dark:text-gray-200">
                                    {{ $activity->message }}
                                    @if($activity->type === 'deleted' && isset($activity->metadata['deleted_by']))
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            (by {{ $activity->metadata['deleted_by'] }}{{ isset($activity->metadata['permanent']) ? ', permanent' : '' }})
                                        </span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4" id="no-activity-message">No recent activity</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
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
@endif
@endpush
