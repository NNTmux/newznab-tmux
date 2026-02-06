@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-edit mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ route('admin.show-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="p-6">
            <form method="POST" action="{{ url('admin/show-edit?id=' . $show['id']) }}">
                @csrf
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="id" value="{{ $show['id'] }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="title"
                                   name="title"
                                   value="{{ $show['title'] ?? '' }}"
                                   readonly
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Title is read-only</p>
                        </div>

                        <!-- Type -->
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type
                            </label>
                            <select id="type"
                                    name="type"
                                    disabled
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">
                                <option value="0" {{ isset($show['type']) && $show['type'] == 0 ? 'selected' : '' }}>TV Show</option>
                                <option value="1" {{ isset($show['type']) && $show['type'] == 1 ? 'selected' : '' }}>Film</option>
                                <option value="2" {{ isset($show['type']) && $show['type'] == 2 ? 'selected' : '' }}>Anime</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Type is read-only</p>
                        </div>

                        <!-- Started Date -->
                        <div>
                            <label for="started" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Started Date
                            </label>
                            <input type="text"
                                   id="started"
                                   name="started"
                                   value="{{ $show['started'] ?? '' }}"
                                   readonly
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">
                        </div>

                        <!-- Country -->
                        <div>
                            <label for="countries_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Country Code
                            </label>
                            <input type="text"
                                   id="countries_id"
                                   name="countries_id"
                                   value="{{ $show['countries_id'] ?? '' }}"
                                   readonly
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">
                        </div>

                        <!-- External IDs -->
                        <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">External IDs</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">TVDB ID</label>
                                    <input type="text"
                                           value="{{ $show['tvdb'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">IMDB ID</label>
                                    <input type="text"
                                           value="{{ $show['imdb'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">TMDB ID</label>
                                    <input type="text"
                                           value="{{ $show['tmdb'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Trakt ID</label>
                                    <input type="text"
                                           value="{{ $show['trakt'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">TVMaze ID</label>
                                    <input type="text"
                                           value="{{ $show['tvmaze'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">AniDB ID</label>
                                    <input type="text"
                                           value="{{ $show['anidb'] ?? '—' }}"
                                           readonly
                                           class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 rounded bg-gray-100">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Cover Image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Cover Image
                            </label>
                            <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                                @if(!empty($show['image']))
                                    <img src="{{ $show['image'] }}"
                                         alt="{{ $show['title'] }}"
                                         class="max-w-full h-auto mx-auto rounded shadow-lg"
                                         style="max-height: 400px;"
                                         data-fallback-src="{{ asset('images/no-cover.png') }}">
                                @else
                                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                                        <i class="fas fa-tv text-6xl mb-3"></i>
                                        <p>No cover image available</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Summary -->
                        <div>
                            <label for="summary" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Summary
                            </label>
                            <textarea id="summary"
                                      name="summary"
                                      rows="8"
                                      readonly
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">{{ $show['summary'] ?? '' }}</textarea>
                        </div>

                        <!-- Publisher -->
                        <div>
                            <label for="publisher" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Publisher
                            </label>
                            <input type="text"
                                   id="publisher"
                                   name="publisher"
                                   value="{{ $show['publisher'] ?? '' }}"
                                   readonly
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-900">
                        </div>

                        <!-- Show Info -->
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Show Information</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">ID:</dt>
                                    <dd class="text-gray-900 dark:text-gray-200 font-mono">{{ $show['id'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Source:</dt>
                                    <dd class="text-gray-900 dark:text-gray-200">
                                        @if(isset($show['source']))
                                            @if($show['source'] == 0)
                                                TVDB
                                            @elseif($show['source'] == 1)
                                                TMDB
                                            @elseif($show['source'] == 2)
                                                Trakt
                                            @elseif($show['source'] == 3)
                                                TVMaze
                                            @else
                                                Unknown
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                    <a href="{{ route('admin.show-list') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                    <div class="text-sm text-yellow-600 dark:text-yellow-400">
                        <i class="fas fa-info-circle mr-1"></i>This is a view-only page. TV show data is automatically fetched from external sources.
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

