@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-file-lines" subtitle="Browse files from storage/logs and search inside the selected log." />

        <x-admin.search-bar>
            @php
                $clearSearchParams = array_filter([
                    'file' => $selectedFile !== '' ? $selectedFile : null,
                    'lines' => $lines,
                ], fn ($value) => $value !== null && $value !== '');
            @endphp

            <form method="GET" action="{{ route('admin.logs.index') }}" class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-2">
                    <label for="file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Log File</label>
                    <select id="file"
                            name="file"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            @disabled(empty($availableLogs))>
                        @forelse($availableLogs as $log)
                            <option value="{{ $log['path'] }}" @selected($selectedFile === $log['path'])>{{ $log['path'] }}</option>
                        @empty
                            <option value="">No log files found</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search selected log"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="lines" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Count</label>
                    <select id="lines"
                            name="lines"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        @foreach($lineOptions as $option)
                            <option value="{{ $option }}" @selected($lines === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-4 flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-2"></i>Apply
                    </button>

                    @if($selectedFile !== '')
                        <a href="{{ route('admin.logs.index', $clearSearchParams) }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                            <i class="fas fa-eraser mr-2"></i>Clear Search
                        </a>
                    @endif

                    <a href="{{ route('admin.logs.index') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                        <i class="fas fa-rotate-left mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </x-admin.search-bar>

        @if(empty($availableLogs))
            <div class="px-6 py-12 text-center">
                <i class="fas fa-file-circle-xmark text-gray-400 dark:text-gray-600 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No log files found</h3>
                <p class="text-gray-500 dark:text-gray-400">The `storage/logs` directory does not currently contain any readable files.</p>
            </div>
        @elseif($selectedLog !== null)
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-semibold">Selected File</p>
                        <p class="text-gray-900 dark:text-gray-100 font-medium break-all">{{ $selectedLog['path'] }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-semibold">Directory</p>
                        <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $selectedLog['directory'] ?? 'storage/logs' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-semibold">File Size</p>
                        <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $selectedLog['human_size'] }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-semibold">Last Modified</p>
                        <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $selectedLog['modified_at']->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>

            @if($isSearchMode)
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
                    @if($searchMatchCount > 0)
                        Found {{ number_format($searchMatchCount) }} matching line{{ $searchMatchCount === 1 ? '' : 's' }} for <span class="font-semibold">"{{ $search }}"</span>.
                    @else
                        No matching lines found for <span class="font-semibold">"{{ $search }}"</span>.
                    @endif
                </div>

                @if($searchResults !== null && $searchResults->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($searchResults as $match)
                            <div class="px-6 py-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400 mb-2">
                                    Line {{ number_format($match['line_number']) }}
                                </div>
                                <pre class="font-mono text-sm whitespace-pre-wrap break-words text-gray-800 dark:text-gray-200">{{ $match['content'] !== '' ? $match['content'] : ' ' }}</pre>
                            </div>
                        @endforeach
                    </div>

                    <x-admin.pagination :paginator="$searchResults" />
                @else
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-magnifying-glass text-gray-400 dark:text-gray-600 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No search results</h3>
                        <p class="text-gray-500 dark:text-gray-400">Try a different search term or clear the search to view the latest lines.</p>
                    </div>
                @endif
            @elseif($tailView !== null)
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
                    Showing the latest {{ number_format($tailView['displayed_line_count']) }} of {{ number_format($tailView['total_lines']) }} line{{ $tailView['total_lines'] === 1 ? '' : 's' }}.
                </div>

                <div class="bg-gray-950 text-green-400 p-6 overflow-x-auto rounded-b-xl">
                    <pre class="font-mono text-sm whitespace-pre-wrap break-words">{{ $tailView['content'] !== '' ? $tailView['content'] : 'Log file is empty.' }}</pre>
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <i class="fas fa-file-lines text-gray-400 dark:text-gray-600 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Select a log file</h3>
                <p class="text-gray-500 dark:text-gray-400">Choose a file from the dropdown above to inspect its contents.</p>
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
