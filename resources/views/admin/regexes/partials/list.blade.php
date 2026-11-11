{{--
    Regex list shared by the collection, release naming and category regex pages.
    `$type` is 'collection', 'release_naming' or 'category'; `$regex` is the paginated list.
--}}
@php
    $regexListTypes = [
        'collection' => ['slug' => 'collection_regexes', 'title' => 'Collection Regex List', 'icon' => 'fas fa-layer-group', 'test' => true,
            'intro' => 'This page lists regular expressions used for grouping binaries into collections.'],
        'release_naming' => ['slug' => 'release_naming_regexes', 'title' => 'Release Naming Regex List', 'icon' => 'fas fa-tag', 'test' => true,
            'intro' => 'This page lists regular expressions used for renaming releases based on their names.'],
        'category' => ['slug' => 'category_regexes', 'title' => 'Category Regex List', 'icon' => 'fas fa-folder-open', 'test' => false,
            'intro' => 'This page lists regular expressions used for categorizing releases.'],
    ];
    $regexList = $regexListTypes[$type];
    $regexEditUrl = url('/admin/'.$regexList['slug'].'-edit');
    $regexTestUrl = url('/admin/'.$regexList['slug'].'-test');
    $regexDeleteRoute = 'admin.'.$regexList['slug'].'-delete';
@endphp

<div class="space-y-6">
    <x-admin.page-header :title="$title ?? $regexList['title']" :icon="$regexList['icon']">
        <x-slot:actions>
            <x-admin.button :href="$regexEditUrl" icon="fas fa-plus">Add New Regex</x-admin.button>
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
                        {{ $regexList['intro'] }}<br>
                        @if($regexList['test'])
                            You can test your regex patterns using the <a href="{{ $regexTestUrl }}" class="font-semibold underline hover:text-info-900 dark:hover:text-info-100">test feature</a>.
                        @else
                            You can recategorize all releases by running <code class="bg-info-100 dark:bg-info-800 text-info-800 dark:text-info-200 px-2 py-1 rounded text-xs">misc/update/update_releases 6 true</code>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div id="message" class="px-6"></div>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
            <form name="groupsearch" action="" method="get" class="max-w-md">
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
                <x-admin.pagination :paginator="$regex" position="top" />
            @endif

            <x-admin.data-table>
                <x-slot:head>
                            <x-admin.th align="center" width="20">ID</x-admin.th>
                            <x-admin.th>Group</x-admin.th>
                            <x-admin.th>Description</x-admin.th>
                            <x-admin.th>Regex</x-admin.th>
                            <x-admin.th align="center" width="24">Ordinal</x-admin.th>
                            <x-admin.th align="center" width="24">Status</x-admin.th>
                            @if($type === 'category')
                                <x-admin.th align="center" width="28">Category</x-admin.th>
                            @endif
                            <x-admin.th align="center" width="28">Actions</x-admin.th>
                </x-slot:head>

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
                                        <x-admin.badge tone="success" icon="fas fa-check-circle">Active</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="danger" icon="fas fa-times-circle">Disabled</x-admin.badge>
                                    @endif
                                </td>
                                @if($type === 'category')
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <x-admin.badge tone="neutral" icon="fas fa-folder-open">{{ $row->categories_id }}</x-admin.badge>
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a aria-label="Edit this regex" href="{{ $regexEditUrl . '?id=' . $row->id }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit this regex">
                                            <i aria-hidden="true" class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route($regexDeleteRoute, $row->id) }}" method="post" class="inline"
                                              x-data="confirmForm"
                                              data-message="Are you sure you want to delete this regex? This action cannot be undone."
                                              data-title="Delete Regex"
                                              data-type="danger"
                                              data-confirm-text="Delete"
                                              @submit.prevent="submit()">
                                            @csrf
                                            <button aria-label="Delete this regex" type="submit" title="Delete this regex"
                                                    class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300">
                                                <i aria-hidden="true" class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>

            <!-- Pagination Bottom -->
            @if(method_exists($regex, 'links'))
                <x-admin.pagination :paginator="$regex" />
            @endif
        @else
            <x-admin.empty-state icon="fas fa-exclamation-triangle" title="No regex patterns found" message="Try a different search term or add a new regex.">
                <x-admin.button :href="$regexEditUrl" icon="fas fa-plus">Add New Regex</x-admin.button>
            </x-admin.empty-state>
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
                    @if($regexList['test'])
                        <a href="{{ $regexTestUrl }}" class="px-4 py-2 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700 dark:hover:bg-purple-800 text-sm">
                            <i aria-hidden="true" class="fas fa-flask mr-2"></i>Test Regex
                        </a>
                    @endif
                    <a href="{{ $regexEditUrl }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 text-sm">
                        <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Regex
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
