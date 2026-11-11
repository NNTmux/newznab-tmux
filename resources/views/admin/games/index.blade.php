@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-gamepad" />
    <x-admin.card>




        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/game-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="gamesearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by game title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
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
                                        <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ url('admin/game-edit?id=' . $game['id']) }}" class="text-link dark:text-primary-400 hover:text-primary-900">
                                        <i aria-hidden="true" class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>
            <x-admin.pagination :paginator="$gamelist->withQueryString()" />
        @else
            @php
                $gamesEmptyMessage = ! empty($lastSearch) ? 'No games found matching "'.$lastSearch.'".' : 'No games found in the database.';
            @endphp
            <x-admin.empty-state icon="fas fa-gamepad" title="No games found" :message="$gamesEmptyMessage" />
        @endif
    </x-admin.card>
</div>
@endsection
