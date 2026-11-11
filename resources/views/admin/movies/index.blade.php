@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-film">
        <x-slot:actions>
            <a href="{{ url('admin/movie-add') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add Movie
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Search Form -->
        <x-admin.filter-panel>
            <form method="GET" action="{{ url('admin/movie-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="moviesearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by IMDB ID or movie title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                        Search
                    </button>
                    @if(!empty($lastSearch))
                        <a href="{{ url('admin/movie-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </x-admin.filter-panel>

        @if(!empty($movielist) && count($movielist) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>IMDB ID</x-admin.th>
                    <x-admin.th>Title</x-admin.th>
                    <x-admin.th>Year</x-admin.th>
                    <x-admin.th>Rating</x-admin.th>
                    <x-admin.th>Genre</x-admin.th>
                    <x-admin.th>Cover</x-admin.th>
                    <x-admin.th>Backdrop</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                @foreach($movielist as $movie)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <a href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $movie->imdbid }}" target="_blank" rel="noopener noreferrer" class="text-link dark:text-primary-400 hover:underline">
                                {{ $movie->imdbid }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $movie->title ?? 'N/A' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $movie->year ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $movie->rating ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Str::limit($movie->genre ?? 'N/A', 30) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($movie->cover == 1)
                                <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($movie->backdrop == 1)
                                <x-admin.badge tone="success" icon="fas fa-check">Yes</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger" icon="fas fa-times">No</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ url('admin/movie-edit?id=' . $movie->imdbid) }}" class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300 mr-3">
                                <i aria-hidden="true" class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.movie-edit') }}" class="inline">
                                @csrf
                                <input type="hidden" name="id" value="{{ $movie->imdbid }}">
                                <input type="hidden" name="update" value="1">
                                <button type="submit" class="text-success-600 dark:text-success-400 hover:text-success-900 dark:hover:text-success-300" title="Update from TMDB">
                                    <i aria-hidden="true" class="fas fa-sync-alt"></i> Update
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
            <x-admin.pagination :paginator="$movielist->withQueryString()" />
        @else
            <x-empty-state
                icon="fas fa-film"
                :title="!empty($lastSearch) ? 'No movies found matching &quot;' . $lastSearch . '&quot;' : 'No movies found'"
                :message="!empty($lastSearch) ? 'Try adjusting your search terms.' : 'No movies found in the database.'"
                :actionUrl="url('admin/movie-add')"
                actionLabel="Add Your First Movie"
                actionIcon="fas fa-plus"
            />
        @endif
    </x-admin.card>
</div>
@endsection
