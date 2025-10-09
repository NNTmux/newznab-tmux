@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-gamepad mr-2"></i>{{ $title }}
                </h1>
            </div>
        </div>

        <!-- Success/Error/Warning Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fa fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
            <form method="GET" action="{{ url('admin/game-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="gamesearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by game title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        Search
                    </button>
                    @if(!empty($lastSearch))
                        <a href="{{ url('admin/game-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Game List Table -->
        @if(!empty($gamelist) && count($gamelist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Publisher</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Genre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ESRB</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Release Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cover</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                        @foreach($gamelist as $game)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $game['id'] ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                        {{ $game['title'] ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $game['publisher'] ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $game['genre'] ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $game['esrb'] ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if(!empty($game['releasedate']))
                                        {{ date('Y-m-d', $game['releasedate']) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if(!empty($game['cover']) && $game['cover'] == 1)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fa fa-check"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fa fa-times"></i> No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ url('admin/game-edit?id=' . $game['id']) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-gamepad text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400 text-lg">
                    @if(!empty($lastSearch))
                        No games found matching "{{ $lastSearch }}".
                    @else
                        No games found in the database.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
@endsection

