@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-gamepad mr-2"></i>{{ $title }}
                </h1>
            </div>
        </div>

        <!-- Success/Error/Warning Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/game-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="gamesearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by game title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <x-button type="submit">Search</x-button>
                    @if(!empty($lastSearch))
                        <x-button-link href="{{ url('admin/game-list') }}" variant="secondary">Clear</x-button-link>
                    @endif
                </div>
            </form>
        </div>

        <!-- Game List Table -->
        @if(!empty($gamelist) && count($gamelist) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Title</x-admin.th>
                    <x-admin.th>Publisher</x-admin.th>
                    <x-admin.th>Genre</x-admin.th>
                    <x-admin.th>ESRB</x-admin.th>
                    <x-admin.th>Release Date</x-admin.th>
                    <x-admin.th>Cover</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

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
                                            <i class="fas fa-check"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times"></i> No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ url('admin/game-edit?id=' . $game['id']) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-900">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>
        @else
            @php
                $gamesEmptyMessage = ! empty($lastSearch) ? 'No games found matching "'.$lastSearch.'".' : 'No games found in the database.';
            @endphp
            <x-admin.empty-state icon="fas fa-gamepad" title="No games found" :message="$gamesEmptyMessage" />
        @endif
    </x-admin.card>
</div>
@endsection

