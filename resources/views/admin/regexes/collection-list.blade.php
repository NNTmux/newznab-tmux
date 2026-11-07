@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Collection Regex List'" icon="fas fa-layer-group">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/collection_regexes-edit')" icon="fas fa-plus">Add New Regex</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-info-50 dark:bg-info-900/20 border-b border-info-100 dark:border-info-800">
            <div class="flex">
                <div class="shrink-0">
                    <i aria-hidden="true" class="fas fa-info-circle text-info-500 dark:text-info-400 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-info-700 dark:text-info-300">
                        This page lists regular expressions used for grouping binaries into collections.<br>
                        You can test your regex patterns using the <a href="{{ url('/admin/collection_regexes-test') }}" class="font-semibold underline hover:text-info-900 dark:hover:text-info-100">test feature</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div id="message" class="px-6"></div>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
            <form name="groupsearch" action="" method="get" class="max-w-md">
                @csrf
                <label for="group" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search by Group:</label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                        </div>
                        <input id="group"
                               type="text"
                               name="group"
                               value="{{ $group }}"
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="Search a group...">
                    </div>
                    <button class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800" type="submit">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        @if($regex && count($regex) > 0)
            <!-- Pagination Top -->
            @if(method_exists($regex, 'links'))
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <x-admin.th align="center" width="20">ID</x-admin.th>
                            <x-admin.th>Group</x-admin.th>
                            <x-admin.th>Description</x-admin.th>
                            <x-admin.th>Regex</x-admin.th>
                            <x-admin.th align="center" width="24">Ordinal</x-admin.th>
                            <x-admin.th align="center" width="24">Status</x-admin.th>
                            <x-admin.th align="center" width="28">Actions</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($regex as $row)
                            <tr id="row-{{ $row->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $row->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <code class="text-gray-800 dark:text-gray-100 px-2 py-1 rounded text-xs font-medium">{{ $row->group_regex }}</code>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="inline-block max-w-[200px] truncate" title="{{ $row->description }}">
                                        {{ $row->description }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="max-w-[200px] wrap-break-word">
                                        <code class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 rounded text-xs break-all" title="{{ regex_display_value($row->regex) }}">
                                            {{ regex_display_value($row->regex) }}
                                        </code>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">{{ $row->ordinal }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($row->status == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-100 dark:bg-success-900/30 text-success-800 dark:text-success-300">
                                            <i aria-hidden="true" class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                            <i aria-hidden="true" class="fas fa-times-circle mr-1"></i>Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a aria-label="Edit this regex" href="{{ url('/admin/collection_regexes-edit?id=' . $row->id) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit this regex">
                                            <i aria-hidden="true" class="fas fa-edit"></i>
                                        </a>
                                                <button aria-label="Delete this regex" type="button"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                        data-delete-regex="{{ $row->id }}"
                                                        data-delete-url="{{ url('/admin/collection_regexes-delete') }}"
                                                        title="Delete this regex">
                                                    <i aria-hidden="true" class="fas fa-trash"></i>
                                                </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bottom -->
            @if(method_exists($regex, 'links'))
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $regex->onEachSide(5)->links() }}
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <i aria-hidden="true" class="fas fa-exclamation-triangle text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No regex patterns found</h3>
                <p class="text-gray-500 mb-4">Try a different search term or add a new regex.</p>
                <a href="{{ url('/admin/collection_regexes-edit') }}" class="inline-flex items-center px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                    <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Regex
                </a>
            </div>
        @endif

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    @if($regex && method_exists($regex, 'total'))
                        Total entries: {{ $regex->total() }}
                    @elseif($regex)
                        Total entries: {{ count($regex) }}
                    @else
                        No entries
                    @endif
                </span>
                <div class="flex gap-2">
                    <a href="{{ url('/admin/collection_regexes-test') }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700 dark:hover:bg-purple-800 text-sm">
                        <i aria-hidden="true" class="fas fa-flask mr-2"></i>Test Regex
                    </a>
                    <a href="{{ url('/admin/collection_regexes-edit') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 text-sm">
                        <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Regex
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
{{-- Regex management functions moved to resources/js/csp-safe.js --}}
