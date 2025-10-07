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
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ number_format($stats['groups'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-layer-group text-2xl text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $stats['active_groups'] ?? 0 }} currently active
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

