@extends('layouts.admin')

@section('content')
@php
    $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES;
@endphp
<div class="space-y-6"
     x-data="adminLogViewer"
     @keydown.window="onGlobalKeydown($event)"
     data-files="{{ json_encode($files, $jsonFlags) }}"
     data-levels="{{ json_encode($levels) }}"
     data-limit-options="{{ json_encode($limitOptions) }}"
     data-default-limit="{{ $defaultLimit }}"
     data-max-files-per-search="{{ $maxFilesPerSearch }}"
     data-initial-file="{{ $initialFile }}"
     data-initial-query="{{ $initialQuery }}"
     data-engine="{{ $engine }}"
     data-index-enabled="{{ $indexEnabled ? '1' : '0' }}"
     data-files-url="{{ route('admin.logs.files') }}"
     data-entries-url="{{ route('admin.logs.entries') }}"
     data-entry-url="{{ route('admin.logs.entry') }}"
     data-search-url="{{ route('admin.logs.search') }}"
     data-facets-url="{{ route('admin.logs.facets') }}"
     data-download-url="{{ route('admin.logs.download') }}"
     data-truncate-url="{{ route('admin.logs.truncate') }}"
     data-destroy-url="{{ route('admin.logs.destroy') }}">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-file-lines" subtitle="Browse entries in storage/logs and search one file or every log at once, by text, level, channel or time. Press / to search." />

        <noscript>
            <div class="px-6 py-4 text-sm text-amber-800 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-200">The log viewer requires JavaScript.</div>
        </noscript>

        @if($files === [])
            <x-admin.empty-state icon="fas fa-file-circle-xmark" title="No log files found" message="The storage/logs directory does not currently contain any readable files." />
        @else
            <div class="flex flex-col lg:flex-row">
                {{-- File sidebar --}}
                <aside class="lg:w-80 lg:shrink-0 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-700">
                    <div class="p-4 space-y-3 lg:sticky lg:top-0">
                        <div class="relative">
                            <i class="fas fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400" aria-hidden="true"></i>
                            <input type="search"
                                   x-model="fileFilter"
                                   placeholder="Filter files"
                                   aria-label="Filter log files"
                                   class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-8 pr-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        </div>

                        <div x-show="isAllScope()" x-cloak class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span x-text="includedLabel()"></span>
                            <span class="flex gap-2">
                                <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="includeAll()">All</button>
                                <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="includeNone()">None</button>
                            </span>
                        </div>

                        <ul class="max-h-72 lg:max-h-[calc(100vh-16rem)] overflow-y-auto space-y-0.5 -mx-1 px-1" role="listbox" aria-label="Log files">
                            <template x-for="file in filteredFiles()" :key="file.path">
                                <li class="flex items-stretch gap-1">
                                    <label x-show="isAllScope()" class="flex items-center px-1" :title="includeTitle(file)">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                               :checked="isIncluded(file.path)"
                                               @change="toggleIncluded(file.path)">
                                    </label>
                                    <button type="button"
                                            class="min-w-0 flex-1 rounded-lg px-2.5 py-2 text-left transition"
                                            :class="fileRowClass(file.path)"
                                            :aria-selected="isSelected(file.path)"
                                            @click="selectFile(file.path)">
                                        <span class="flex items-center gap-2">
                                            <span x-show="file.active" class="h-2 w-2 shrink-0 rounded-full bg-green-500" title="Written to recently"></span>
                                            <span class="truncate text-sm font-medium" x-text="file.name"></span>
                                            <i x-show="hasIndexState(file)" class="fas fa-bolt ml-auto shrink-0 text-[10px]" :class="indexStateClass(file)" :title="indexStateTitle(file)" aria-hidden="true"></i>
                                        </span>
                                        <span class="mt-0.5 flex justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="truncate" x-text="fileDirectory(file)"></span>
                                            <span class="shrink-0" :title="absoluteTime(file.modified_at)" x-text="fileMeta(file)"></span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </aside>

                {{-- Main panel --}}
                <section class="min-w-0 flex-1">
                    {{-- Toolbar --}}
                    <div class="space-y-3 border-b border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                            <div class="relative flex-1">
                                <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400" aria-hidden="true"></i>
                                <input type="text"
                                       x-ref="search"
                                       x-model="query"
                                       @input="onQueryInput()"
                                       @keydown="onSearchKeydown($event)"
                                       placeholder="Search logs…  ( / )"
                                       aria-label="Search logs"
                                       autocomplete="off"
                                       spellcheck="false"
                                       class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-9 pr-24 font-mono text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <div class="absolute right-1.5 top-1/2 flex -translate-y-1/2 gap-1">
                                    <button type="button"
                                            class="rounded-md border px-2 py-1 font-mono text-xs font-semibold transition"
                                            :class="toggleButtonClass(caseSensitive)"
                                            :aria-pressed="caseSensitive"
                                            title="Match case (ASCII letters)"
                                            @click="toggleCase()">Aa</button>
                                    <button type="button"
                                            class="rounded-md border px-2 py-1 font-mono text-xs font-semibold transition"
                                            :class="toggleButtonClass(regex)"
                                            :aria-pressed="regex"
                                            title="Regular expression (PCRE)"
                                            @click="toggleRegex()">.*</button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 dark:border-gray-600 dark:bg-gray-800" role="group" aria-label="Search scope">
                                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="scopeButtonClass('file')" @click="setScope('file')">
                                        <i class="fas fa-file mr-1" aria-hidden="true"></i>This file
                                    </button>
                                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="scopeButtonClass('all')" @click="setScope('all')">
                                        <i class="fas fa-layer-group mr-1" aria-hidden="true"></i>All logs
                                    </button>
                                </div>

                                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="sr-only sm:not-sr-only">Show</span>
                                    <select x-model.number="limit"
                                            @change="onLimitChange()"
                                            aria-label="Entries per page"
                                            class="rounded-lg border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                        <template x-for="option in limitOptions" :key="option">
                                            <option :value="option" x-text="option" :selected="option === limit"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5" :title="countsTitle()">
                            <template x-for="level in levelOptions" :key="level">
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide transition"
                                        :class="levelChipClass(level)"
                                        :aria-pressed="isLevelActive(level)"
                                        @click="toggleLevel(level)">
                                    <span x-text="level"></span>
                                    <span class="rounded-full bg-black/10 px-1.5 text-[10px] dark:bg-white/10" x-show="levelCount(level) > 0" x-text="levelCountLabel(level)"></span>
                                </button>
                            </template>
                            <button type="button" x-show="hasLevels()" x-cloak class="ml-1 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="clearLevels()">Clear levels</button>
                        </div>

                        <div x-show="hasChannelOptions()" x-cloak class="flex flex-wrap items-center gap-1.5" :title="countsTitle()">
                            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Channels</span>
                            <template x-for="channel in channelOptions()" :key="channel">
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-xs transition"
                                        :class="channelChipClass(channel)"
                                        :aria-pressed="isChannelActive(channel)"
                                        @click="toggleChannel(channel)">
                                    <span x-text="channel"></span>
                                    <span class="rounded-full bg-black/10 px-1.5 text-[10px] dark:bg-white/10" x-show="channelCount(channel) > 0" x-text="channelCountLabel(channel)"></span>
                                </button>
                            </template>
                            <button type="button" x-show="hasChannels()" x-cloak class="ml-1 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="clearChannels()">Clear channels</button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <label class="flex items-center gap-1.5">
                                <span>From</span>
                                <input type="datetime-local"
                                       step="60"
                                       x-model="from"
                                       @change="onRangeChange()"
                                       aria-label="Entries logged from"
                                       class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            </label>
                            <label class="flex items-center gap-1.5">
                                <span>To</span>
                                <input type="datetime-local"
                                       step="60"
                                       x-model="to"
                                       @change="onRangeChange()"
                                       aria-label="Entries logged until"
                                       class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            </label>
                            <button type="button" x-show="hasTimeRange()" x-cloak class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="clearRange()">Clear time range</button>
                        </div>

                        <p x-show="searchError" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="searchError"></p>
                    </div>

                    {{-- Selected file header (browse mode) --}}
                    <div x-show="isBrowseMode()" class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-700">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="selectedPath"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="selectedMeta()"></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" @click="loadEntries()" title="Reload the latest entries">
                                <i class="fas fa-rotate" aria-hidden="true"></i><span>Refresh</span>
                            </button>
                            <a class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" :href="downloadHref()" title="Download the raw file">
                                <i class="fas fa-download" aria-hidden="true"></i><span>Download</span>
                            </a>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-900/30" @click="truncateFile()" title="Empty this log file">
                                <i class="fas fa-eraser" aria-hidden="true"></i><span>Truncate</span>
                            </button>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-300 dark:hover:bg-red-900/30" :disabled="canDelete() === false" :title="deleteTitle()" @click="deleteFile()">
                                <i class="fas fa-trash" aria-hidden="true"></i><span>Delete</span>
                            </button>
                        </div>
                    </div>

                    {{-- Search progress --}}
                    <div x-show="isSearchMode()" x-cloak class="border-b border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="flex items-center gap-2">
                                <i x-show="searching" class="fas fa-circle-notch fa-spin text-blue-500" aria-hidden="true"></i>
                                <span x-text="progressLabel()"></span>
                            </span>
                            <span class="flex gap-3 text-xs">
                                <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="expandAllGroups()">Expand all</button>
                                <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="collapseAllGroups()">Collapse all</button>
                                <button type="button" class="font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="clearSearch()">Clear search</button>
                            </span>
                        </div>
                        <div class="mt-2 h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" x-show="searching">
                            <div class="h-full bg-blue-500 transition-all duration-300" :style="progressStyle()"></div>
                        </div>
                    </div>

                    {{-- Browse mode --}}
                    <div x-show="isBrowseMode()">
                        <div x-show="isBrowsingFromAnchor()" x-cloak class="flex items-center justify-between gap-2 border-b border-yellow-200 bg-yellow-50 px-4 py-2 text-sm text-yellow-800 sm:px-6 dark:border-yellow-900 dark:bg-yellow-900/20 dark:text-yellow-200">
                            <span>Showing entries from the selected match backwards.</span>
                            <button type="button" class="font-semibold hover:underline" @click="jumpToLatest()">Jump to latest</button>
                        </div>

                        <div x-show="loading" class="space-y-3 p-6" aria-live="polite">
                            <div class="h-4 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                            <div class="h-4 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                            <div class="h-4 w-5/6 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                        </div>

                        <div x-show="error" x-cloak class="m-6 flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/30 dark:text-red-200">
                            <span x-text="error"></span>
                            <button type="button" class="font-semibold hover:underline" @click="loadEntries()">Retry</button>
                        </div>

                        <div x-show="showBrowseEmpty()" x-cloak class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox mb-2 text-2xl" aria-hidden="true"></i>
                            <p x-show="hasLevels()">No entries with the selected levels in the scanned range.</p>
                            <p x-show="hasLevels() === false">This log file is empty.</p>
                        </div>

                        <ol x-show="loading === false" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="entry in entries" :key="entry._key">
                                @include('admin.logs.partials.entry', ['showOpenInFile' => false])
                            </template>
                        </ol>

                        <div x-show="hasOlder()" x-cloak class="border-t border-gray-200 px-6 py-4 text-center dark:border-gray-700">
                            <p x-show="budgetExhausted" class="mb-2 text-xs text-gray-500 dark:text-gray-400">Scanned 32 MB without filling the page. Continue to scan further back.</p>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" :disabled="loadingMore" @click="loadOlder()">
                                <i class="fas" :class="loadingMore ? 'fa-circle-notch fa-spin' : 'fa-angles-down'" aria-hidden="true"></i>
                                <span>Load older entries</span>
                            </button>
                        </div>
                    </div>

                    {{-- Search mode --}}
                    <div x-show="isSearchMode()" x-cloak>
                        <div x-show="showSearchEmpty()" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-magnifying-glass mb-2 text-2xl" aria-hidden="true"></i>
                            <p>No matches found.</p>
                        </div>

                        <template x-for="group in visibleGroups()" :key="group.path">
                            <div class="border-b border-gray-200 dark:border-gray-700">
                                <button type="button"
                                        class="flex w-full items-center gap-3 bg-gray-50 px-4 py-2.5 text-left hover:bg-gray-100 sm:px-6 dark:bg-gray-900 dark:hover:bg-gray-800"
                                        :aria-expanded="group.open"
                                        @click="toggleGroup(group.path)">
                                    <i class="fas w-3 text-xs text-gray-500" :class="groupChevronClass(group)" aria-hidden="true"></i>
                                    <span class="min-w-0 flex-1 truncate font-mono text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="group.path"></span>
                                    <span x-show="hasEngine(group)" class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="engineBadgeClass(group)" :title="engineTitle(group)" x-text="engineLabel(group)"></span>
                                    <span x-show="group.timedOut" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200" title="The search timed out; results may be incomplete">timed out</span>
                                    <span x-show="group.error" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/50 dark:text-red-200" :title="group.error">error</span>
                                    <span class="shrink-0 text-xs text-gray-600 dark:text-gray-400" x-text="groupCountLabel(group)"></span>
                                </button>

                                <template x-if="group.open">
                                    <div>
                                        <p x-show="group.error" class="px-6 py-2 text-sm text-red-700 dark:text-red-300" x-text="group.error"></p>
                                        <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                                            <template x-for="entry in group.entries" :key="entry._key">
                                                @include('admin.logs.partials.entry', ['showOpenInFile' => true])
                                            </template>
                                        </ol>
                                        <div class="flex items-center justify-between gap-2 px-6 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span x-text="groupShownLabel(group)"></span>
                                            <button type="button"
                                                    x-show="group.hasMore"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    :disabled="group.loadingMore"
                                                    @click="loadMoreForGroup(group.path)">
                                                <i class="fas" :class="group.loadingMore ? 'fa-circle-notch fa-spin' : 'fa-angles-down'" aria-hidden="true"></i>
                                                <span>Load older matches</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
