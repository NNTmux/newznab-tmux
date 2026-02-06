@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fa fa-tag mr-2"></i>{{ $title ?? 'Release Naming Regex List' }}
                </h1>
                <a href="{{ url('/admin/release_naming_regexes-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-plus mr-2"></i>Add New Regex
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa fa-info-circle text-blue-500 dark:text-blue-400 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        This page lists regular expressions used for renaming releases based on their names.<br>
                        You can test your regex patterns using the <a href="{{ url('/admin/release_naming_regexes-test') }}" class="font-semibold underline hover:text-blue-900 dark:hover:text-blue-100">test feature</a>.
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
                            <i class="fa fa-search text-gray-400"></i>
                        </div>
                        <input id="group"
                               type="text"
                               name="group"
                               value="{{ $group }}"
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search a group...">
                    </div>
                    <button class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800" type="submit">
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
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Regex</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Ordinal</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Actions</th>
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
                                    <div class="max-w-[200px] break-words">
                                        <code class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 rounded text-xs break-all" title="{{ htmlspecialchars($row->regex) }}">
                                            {{ htmlspecialchars($row->regex) }}
                                        </code>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 dark:text-gray-100">{{ $row->ordinal }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($row->status == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                            <i class="fa fa-check-circle mr-1"></i>Active
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                            <i class="fa fa-times-circle mr-1"></i>Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a href="{{ url('/admin/release_naming_regexes-edit?id=' . $row->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit this regex">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                                <button type="button"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                        data-delete-regex="{{ $row->id }}"
                                                        data-delete-url="{{ url('/admin/release_naming_regexes-delete') }}"
                                                        title="Delete this regex">
                                                    <i class="fa fa-trash"></i>
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
                <i class="fa fa-exclamation-triangle text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No regex patterns found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Try a different search term or add a new regex.</p>
                <a href="{{ url('/admin/release_naming_regexes-edit') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-plus mr-2"></i>Add New Regex
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
                    <a href="{{ url('/admin/release_naming_regexes-test') }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700 dark:hover:bg-purple-800 text-sm">
                        <i class="fa fa-flask mr-2"></i>Test Regex
                    </a>
                    <a href="{{ url('/admin/release_naming_regexes-edit') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm">
                        <i class="fa fa-plus mr-2"></i>Add New Regex
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
{{-- Regex management functions moved to resources/js/csp-safe.js --}}
