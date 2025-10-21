@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-tv mr-2"></i>{{ $title }}
                </h1>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ $tvshowlist->total() }} shows
                </div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('admin/show-list') }}" class="flex items-center space-x-4">
                <div class="flex-1">
                    <label for="showname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search TV Shows
                    </label>
                    <input type="text"
                           id="showname"
                           name="showname"
                           value="{{ $showname }}"
                           placeholder="Enter show name..."
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    @if($showname)
                        <a href="{{ url('admin/show-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- TV Shows Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cover</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Started</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IDs</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Publisher</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tvshowlist as $show)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $coverPath = public_path('covers/tvshows/' . $show->id . '.jpg');
                                    $hasCover = file_exists($coverPath);
                                @endphp
                                @if($hasCover)
                                    <img src="{{ asset('covers/tvshows/' . $show->id . '.jpg') }}"
                                         alt="{{ $show->title }}"
                                         class="h-16 w-12 object-cover rounded shadow"
                                         loading="lazy">
                                @else
                                    <div class="h-16 w-12 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <i class="fas fa-tv text-gray-400 text-sm"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                    {{ $show->title }}
                                </div>
                                @if(!empty($show->summary))
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-md line-clamp-2">
                                        {{ Str::limit($show->summary, 100) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($show->type == 0)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <i class="fas fa-tv mr-1"></i>TV
                                    </span>
                                @elseif($show->type == 1)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                        <i class="fas fa-film mr-1"></i>Film
                                    </span>
                                @elseif($show->type == 2)
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200">
                                        <i class="fas fa-dragon mr-1"></i>Anime
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $show->started ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                <div class="space-y-1">
                                    @if($show->tvdb)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">TVDB:</span> {{ $show->tvdb }}
                                        </div>
                                    @endif
                                    @if($show->imdb)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">IMDB:</span> {{ $show->imdb }}
                                        </div>
                                    @endif
                                    @if($show->tmdb)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">TMDB:</span> {{ $show->tmdb }}
                                        </div>
                                    @endif
                                    @if($show->trakt)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">Trakt:</span> {{ $show->trakt }}
                                        </div>
                                    @endif
                                    @if($show->tvmaze)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">TVMaze:</span> {{ $show->tvmaze }}
                                        </div>
                                    @endif
                                    @if($show->anidb)
                                        <div class="text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">AniDB:</span> {{ $show->anidb }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                {{ $show->publisher ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <a href="{{ url('admin/show-edit?id=' . $show->id) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="{{ url('admin/show-remove/' . $show->id) }}"
                                   class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                   title="Remove from Releases">
                                    <i class="fas fa-unlink"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                @if($showname)
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-search text-4xl mb-3"></i>
                                        <p class="text-lg">No TV shows found for "{{ $showname }}"</p>
                                        <a href="{{ url('admin/show-list') }}" class="mt-3 text-blue-600 dark:text-blue-400 hover:underline">
                                            Clear search and view all
                                        </a>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-tv text-4xl mb-3"></i>
                                        <p class="text-lg">No TV shows available</p>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Showing {{ $tvshowlist->firstItem() ?? 0 }} to {{ $tvshowlist->lastItem() ?? 0 }} of {{ $tvshowlist->total() }} shows
                </div>
                <div>
                    {{ $tvshowlist->appends(['showname' => $showname])->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to resources/css/csp-safe.css --}}
@endsection

