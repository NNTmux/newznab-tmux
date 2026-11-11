@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-music" />
    <x-admin.card>




        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/music-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="musicsearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by artist, album title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                        Search
                    </button>
                    @if(!empty($lastSearch))
                        <a href="{{ url('admin/music-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Music List Table -->
        @if(!empty($musicList) && count($musicList) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Title</x-admin.th>
                    <x-admin.th>Artist</x-admin.th>
                    <x-admin.th>Publisher</x-admin.th>
                    <x-admin.th>Year</x-admin.th>
                    <x-admin.th>Genre</x-admin.th>
                    <x-admin.th>Tracks</x-admin.th>
                    <x-admin.th>Cover</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                        @foreach($musicList as $music)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    {{ $music->id ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                        {{ $music->title ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $music->artist ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $music->publisher ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $music->year ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Illuminate\Support\Str::limit($music->genre ?? 'N/A', 30) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $music->tracks ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if(!empty($music->cover) && $music->cover == 1)
                                        <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ url('admin/music-edit?id=' . $music->id) }}" class="text-link dark:text-primary-400 hover:text-primary-900">
                                        <i aria-hidden="true" class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>
            <x-admin.pagination :paginator="$musicList->withQueryString()" />
        @else
            <div class="px-6 py-12 text-center">
                <i aria-hidden="true" class="fas fa-music text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400 text-lg">
                    @if(!empty($lastSearch))
                        No music found matching "{{ $lastSearch }}".
                    @else
                        No music found in the database.
                    @endif
                </p>
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
