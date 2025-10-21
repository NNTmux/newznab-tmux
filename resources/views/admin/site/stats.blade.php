@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-3">
            <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl shadow-md">
                <i class="fa fa-chart-bar text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-700 dark:text-white">{{ $title }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overview of your site's performance and activity</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Grabbers -->
        @if(!empty($topgrabs) && count($topgrabs) > 0)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-300 dark:border-gray-700 hover:shadow-lg transition-shadow duration-300">
                <div class="bg-gradient-to-r from-blue-400 to-blue-500 px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-blue-700 bg-opacity-40 rounded-lg">
                            <i class="fa fa-trophy text-blue-100 text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold text-white">Top Grabbers</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($topgrabs as $index => $grab)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 border border-gray-200 dark:border-transparent">
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-600 dark:bg-gray-500 text-white font-bold text-sm shadow-sm">
                                        {{ $index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-700 dark:text-gray-100">{{ $grab['username'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-4 py-2 bg-blue-500 text-white rounded-full text-sm font-bold shadow-md">
                                        {{ number_format($grab['grabs']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Top Downloads -->
        @if(!empty($topdownloads) && count($topdownloads) > 0)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-300 dark:border-gray-700 hover:shadow-lg transition-shadow duration-300">
                <div class="bg-gradient-to-r from-purple-400 to-purple-500 px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-purple-700 bg-opacity-40 rounded-lg">
                            <i class="fa fa-download text-purple-100 text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold text-white">Top Downloads</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($topdownloads as $index => $download)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 border border-gray-200 dark:border-transparent">
                                <div class="flex items-center space-x-4 flex-1 min-w-0">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-400 dark:bg-purple-600 text-white font-bold text-sm flex-shrink-0 shadow-sm">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-700 dark:text-gray-100 truncate" title="{{ $download['searchname'] }}">
                                            {{ $download['searchname'] }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2 ml-4 flex-shrink-0">
                                    <span class="px-4 py-2 bg-purple-600 dark:bg-purple-500 text-white rounded-full text-sm font-bold shadow-md">
                                        {{ number_format($download['grabs']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Recently Added -->
        @if(!empty($recent) && count($recent) > 0)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-300 dark:border-gray-700 hover:shadow-lg transition-shadow duration-300">
                <div class="bg-gradient-to-r from-green-400 to-green-500 px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-green-700 bg-opacity-40 rounded-lg">
                            <i class="fa fa-clock text-green-100 text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold text-white">Recently Added</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($recent as $item)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 border border-gray-200 dark:border-transparent">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-8 h-8 bg-green-400 dark:bg-green-600 rounded-lg shadow-sm">
                                        <i class="fa fa-folder text-white text-sm"></i>
                                    </div>
                                    <p class="font-semibold text-gray-700 dark:text-gray-100">{{ $item['category'] }}</p>
                                </div>
                                <span class="px-4 py-2 bg-green-600 dark:bg-green-500 text-white rounded-full text-sm font-bold shadow-md">
                                    {{ number_format($item['count']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Users by Month -->
        @if(!empty($usersbymonth) && count($usersbymonth) > 0)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-300 dark:border-gray-700 hover:shadow-lg transition-shadow duration-300">
                <div class="bg-gradient-to-r from-indigo-400 to-indigo-500 px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-indigo-700 bg-opacity-40 rounded-lg">
                            <i class="fa fa-calendar text-indigo-100 text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold text-white">User Signups by Month</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($usersbymonth as $month)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 border border-gray-200 dark:border-transparent">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-8 h-8 bg-indigo-400 dark:bg-indigo-600 rounded-lg shadow-sm">
                                        <i class="fa fa-users text-white text-sm"></i>
                                    </div>
                                    <p class="font-semibold text-gray-700 dark:text-gray-100">{{ $month['month'] }}</p>
                                </div>
                                <span class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white rounded-full text-sm font-bold shadow-md">
                                    {{ number_format($month['signups']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Users by Role -->
        @if(!empty($usersbyrole) && count($usersbyrole) > 0)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border border-gray-300 dark:border-gray-700 hover:shadow-lg transition-shadow duration-300">
                <div class="bg-gradient-to-r from-pink-400 to-pink-500 px-6 py-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-pink-700 bg-opacity-40 rounded-lg">
                            <i class="fa fa-user-shield text-pink-100 text-lg"></i>
                        </div>
                        <h2 class="text-xl font-bold text-white">Users by Role</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach($usersbyrole as $role)
                            <div class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200 border border-gray-200 dark:border-transparent">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center justify-center w-8 h-8 bg-pink-400 dark:bg-pink-600 rounded-lg shadow-sm">
                                        <i class="fa fa-shield-alt text-white text-sm"></i>
                                    </div>
                                    <p class="font-semibold text-gray-700 dark:text-gray-100 capitalize">{{ $role['role'] }}</p>
                                </div>
                                <span class="px-4 py-2 bg-pink-600 dark:bg-pink-500 text-white rounded-full text-sm font-bold shadow-md">
                                    {{ number_format($role['users']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if(empty($topgrabs) && empty($topdownloads) && empty($recent) && empty($usersbymonth) && empty($usersbyrole))
        <div class="bg-gray-50 dark:bg-gray-800 rounded-xl shadow-md p-12 text-center border border-gray-300 dark:border-gray-700">
            <div class="flex justify-center mb-6">
                <div class="flex items-center justify-center w-24 h-24 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600 rounded-full">
                    <i class="fa fa-chart-bar text-gray-500 dark:text-gray-500 text-4xl"></i>
                </div>
            </div>
            <h3 class="text-2xl font-bold text-gray-700 dark:text-gray-100 mb-3">No statistics available</h3>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Statistics will appear here once data is collected.</p>
        </div>
    @endif
</div>
@endsection

