@extends('layouts.admin')

@section('content')
<div x-data="adminGroups" class="space-y-6" data-ajax-url="{{ url('/admin/ajax') }}" data-csrf-token="{{ csrf_token() }}">
    <x-admin.card>
        <x-admin.page-header :title="$title ?? 'Group List'" icon="fas fa-users" subtitle="Activate, backfill, reset, and purge indexed Usenet groups.">
            <x-slot:actions>
                <x-admin.button :href="url('/admin/group-list-active')" icon="fas fa-check-circle">Active</x-admin.button>
                <x-admin.button :href="url('/admin/group-list-inactive')" tone="gray" icon="fas fa-times-circle">Inactive</x-admin.button>
                <x-admin.button :href="url('/admin/group-list')" tone="gray" icon="fas fa-list">All</x-admin.button>
                <x-admin.button :href="url('/admin/group-bulk')" tone="success" icon="fas fa-plus-circle">Bulk Add</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-900">
            <div class="flex">
                <i class="fas fa-info-circle text-blue-500 dark:text-blue-400 text-xl mr-3"></i>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Below is a list of all usenet groups available to be indexed. Click 'Activate' to start indexing a group.
                    Backfill works independently of active.
                </p>
            </div>
        </div>

        @if(isset($msg) && $msg != '')
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-900 rounded-lg" id="message">
                <p class="text-green-800 dark:text-green-300">{{ $msg }}</p>
            </div>
        @endif

        @php
            $dateFilters = ['first_post' => 'First Post', 'last_post' => 'Last Post', 'last_updated' => 'Last Updated'];
            $numberFilters = ['releases' => 'Releases', 'min_files' => 'Min Files', 'min_size' => 'Min Size (MB)', 'backfill_days' => 'Backfill Days'];
            $filterValue = static function (string $field) use ($filters, $groupname): string {
                $value = old($field, $field === 'groupname' ? $groupname : ($filters[$field] ?? ''));

                return is_scalar($value) ? (string) $value : '';
            };
            $advancedFilters = false;
            foreach (array_keys($dateFilters + $numberFilters) as $field) {
                foreach (isset($dateFilters[$field]) ? ['from', 'to'] : ['min', 'max'] as $bound) {
                    if ($filterValue($field.'_'.$bound) !== '') {
                        $advancedFilters = true;
                    }
                }
            }
            $filterInputClass = 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100';
        @endphp

        <x-admin.action-bar>
            <form name="groupsearch" method="GET" action="{{ route('admin.group-list') }}" class="w-full space-y-4">
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                @if(isset($errors) && $errors->any())
                    <div role="alert" class="text-sm text-red-600 dark:text-red-400">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 xl:items-end">
                    <div>
                        <label for="groupname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group</label>
                        <input id="groupname" type="search" name="groupname" value="{{ $filterValue('groupname') }}"
                               class="{{ $filterInputClass }}" placeholder="Search for group...">
                    </div>
                    <div>
                        <label for="active-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select id="active-filter" name="active" class="{{ $filterInputClass }}">
                            <option value="">All statuses</option>
                            <option value="1" @selected($filterValue('active') === '1')>Active</option>
                            <option value="0" @selected($filterValue('active') === '0')>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="backfill-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Backfill</label>
                        <select id="backfill-filter" name="backfill" class="{{ $filterInputClass }}">
                            <option value="">All backfill states</option>
                            <option value="1" @selected($filterValue('backfill') === '1')>Enabled</option>
                            <option value="0" @selected($filterValue('backfill') === '0')>Disabled</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-admin.button type="submit" icon="fas fa-filter">Apply filters</x-admin.button>
                        <x-admin.button :href="route('admin.group-list')" tone="gray" icon="fas fa-xmark">Clear filters</x-admin.button>
                    </div>
                </div>
                <details @if($advancedFilters) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-blue-600 dark:text-blue-400">Date and number filters</summary>
                    <div class="grid grid-cols-1 gap-4 mt-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($dateFilters as $field => $label)
                            <fieldset class="min-w-0">
                                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(['from' => 'From', 'to' => 'Through'] as $bound => $boundLabel)
                                        <div>
                                            <label for="{{ $field }}_{{ $bound }}" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $boundLabel }}</label>
                                            <input id="{{ $field }}_{{ $bound }}" type="date" name="{{ $field }}_{{ $bound }}"
                                                   value="{{ $filterValue($field.'_'.$bound) }}" class="{{ $filterInputClass }}">
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                        @foreach($numberFilters as $field => $label)
                            <fieldset class="min-w-0">
                                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(['min' => 'Minimum', 'max' => 'Maximum'] as $bound => $boundLabel)
                                        <div>
                                            <label for="{{ $field }}_{{ $bound }}" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $boundLabel }}</label>
                                            <input id="{{ $field }}_{{ $bound }}" type="number" min="0" step="{{ $field === 'min_size' ? 'any' : '1' }}"
                                                   name="{{ $field }}_{{ $bound }}" value="{{ $filterValue($field.'_'.$bound) }}" class="{{ $filterInputClass }}">
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Ranges include both endpoints. Unconfigured minimum files and sizes count as zero.</p>
                </details>
            </form>
        </x-admin.action-bar>

        @if($grouplist && $grouplist->count() > 0)
            <x-admin.action-bar>
                <div class="grid w-full grid-cols-1 xl:grid-cols-2 gap-4">

                    <!-- Pagination -->
                    <div class="flex justify-center items-center">
                        {{ $grouplist->onEachSide(5)->links() }}
                    </div>

                    <!-- Bulk Actions -->
                    <div class="flex justify-end items-center">
                        <div class="flex gap-2 items-center">
                            <!-- Selection Counter -->
                            <span id="selection-counter" class="hidden text-sm text-gray-600 dark:text-gray-400 mr-2">
                                <span id="selected-count">0</span> selected
                            </span>
                            <x-admin.button type="button"
                                    id="reset-selected-btn"
                                    @click="handleAction('show-reset-selected-modal')"
                                    tone="warning"
                                    icon="fas fa-refresh"
                                    class="hidden">
                                Reset Selected
                            </x-admin.button>
                            <x-admin.button type="button"
                                    @click="handleAction('show-reset-modal')"
                                    tone="warning"
                                    icon="fas fa-refresh">
                                Reset All
                            </x-admin.button>
                            <x-admin.button type="button"
                                    @click="handleAction('show-purge-modal')"
                                    tone="danger"
                                    icon="fas fa-trash">
                                Purge All
                            </x-admin.button>
                        </div>
                    </div>
                </div>
            </x-admin.action-bar>

            <!-- Groups Table -->
            <x-admin.data-table sticky>
                <x-slot:head>
                            <x-admin.th align="center" class="w-12">
                                <input type="checkbox"
                                       id="select-all-groups"
                                       x-model="allChecked"
                                       @change="toggleAllCheckboxes()"
                                       class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                       title="Select all groups on this page">
                            </x-admin.th>
                            @foreach(['name' => 'Group', 'first_post' => 'First Post', 'last_post' => 'Last Post', 'last_updated' => 'Last Updated', 'active' => 'Status', 'backfill' => 'Backfill', 'releases' => 'Releases', 'min_files' => 'Min Files', 'min_size' => 'Min Size', 'backfill_days' => 'Backfill Days'] as $field => $label)
                                @php
                                    $isSorted = $filters['sort'] === $field;
                                    $nextDirection = $isSorted && $filters['direction'] === 'asc' ? 'desc' : 'asc';
                                    $sortUrl = request()->url().'?'.http_build_query(array_replace($listQuery, ['sort' => $field, 'direction' => $nextDirection]));
                                    $sortIcon = $isSorted ? ($filters['direction'] === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
                                @endphp
                                <x-admin.th :align="in_array($field, ['name', 'first_post', 'last_post', 'last_updated']) ? 'left' : 'center'"
                                            :aria-sort="$isSorted ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending') : 'none'">
                                    <a href="{{ $sortUrl }}" class="inline-flex items-center gap-2 rounded hover:text-blue-600 dark:hover:text-blue-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                       aria-label="Sort {{ $label }} {{ $nextDirection === 'asc' ? 'ascending' : 'descending' }}">
                                        {{ $label }} <i class="fas {{ $sortIcon }}" aria-hidden="true"></i>
                                    </a>
                                </x-admin.th>
                            @endforeach
                            <x-admin.th align="center" class="w-40">Actions</x-admin.th>
                </x-slot:head>
                        @foreach($grouplist as $group)
                            <tr id="grouprow-{{ $group->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700 group-row">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox"
                                           class="group-checkbox form-checkbox h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                           data-group-id="{{ $group->id }}"
                                           data-group-name="{{ $group->name }}"
                                           @change="onGroupCheckboxChange()">
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ url('/admin/group-edit?id=' . $group->id) }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        {{ str_replace('alt.binaries', 'a.b', $group->name) }}
                                    </a>
                                    @if($group->description)
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $group->description }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-gray-900 dark:text-gray-100">{{ $group->first_record_postdate }}</span>
                                        <small class="text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($group->first_record_postdate)->diffForHumans() }}</small>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $group->last_record_postdate }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" title="{{ $group->last_updated }}">
                                    {{ \Carbon\Carbon::parse($group->last_updated)->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-center" id="group-{{ $group->id }}">
                                    @if($group->active == 1)
                                        <button type="button"
                                                @click="handleAction('toggle-group-status', '{{ $group->id }}', '0')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 hover:bg-green-200">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="handleAction('toggle-group-status', '{{ $group->id }}', '1')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i class="fas fa-times-circle mr-1"></i>Inactive
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center" id="backfill-{{ $group->id }}">
                                    @if($group->backfill == 1)
                                        <button type="button"
                                                @click="handleAction('toggle-backfill', '{{ $group->id }}', '0')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200">
                                            <i class="fas fa-check-circle mr-1"></i>Enabled
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="handleAction('toggle-backfill', '{{ $group->id }}', '1')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i class="fas fa-times-circle mr-1"></i>Disabled
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ $group->num_releases ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(empty($group->minfilestoformrelease))
                                        <span class="text-gray-400 dark:text-gray-500">n/a</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            {{ $group->minfilestoformrelease }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(empty($group->minsizetoformrelease))
                                        <span class="text-gray-400 dark:text-gray-500">n/a</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            {{ human_filesize($group->minsizetoformrelease) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ $group->backfill_target }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center" id="groupdel-{{ $group->id }}">
                                    <div class="flex gap-1 justify-center">
                                        <a href="{{ url('/admin/group-edit?id=' . $group->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit this group">
                                            <i class="fas fa-pencil"></i>
                                        </a>
                                        <button type="button"
                                                @click="handleAction('reset-group', '{{ $group->id }}')"
                                                class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300"
                                                title="Reset this group">
                                            <i class="fas fa-refresh"></i>
                                        </button>
                                        <button type="button"
                                                @click="handleAction('delete-group', '{{ $group->id }}')"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                title="Delete this group">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button type="button"
                                                @click="handleAction('purge-group', '{{ $group->id }}')"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                title="Purge this group">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ $grouplist->count() }} of {{ $grouplist->total() }} groups
                    </span>
                    <div>
                        {{ $grouplist->onEachSide(5)->links() }}
                    </div>
                </div>
            </div>
        @else
            <x-admin.empty-state icon="fas fa-exclamation-triangle" title="No groups match" message="Clear or adjust the filters, or add groups if none have been added yet.">
                <x-admin.button :href="url('/admin/group-bulk')" tone="success" icon="fas fa-plus-circle" class="mt-4">Add Groups</x-admin.button>
            </x-admin.empty-state>
        @endif
    </x-admin.card>

    <!-- Reset All Modal -->
    <div x-show="resetAllOpen"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800"
         @click.outside="handleAction('hide-reset-modal')">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset All Groups</h3>
            <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to reset all groups?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will reset the article pointers for all groups back to their current state.
            </p>
            <div class="flex justify-end gap-3">
                <button type="button"
                        @click="handleAction('hide-reset-modal')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        @click="handleAction('reset-all')"
                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    Reset All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Purge All Modal -->
<div x-show="purgeAllOpen"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800"
         @click.outside="handleAction('hide-purge-modal')">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Purge All Groups</h3>
            <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to purge all groups?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will delete all releases and binaries for all groups. This action cannot be undone!
            </p>
            <div class="flex justify-end gap-3">
                <button type="button"
                        @click="handleAction('hide-purge-modal')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        @click="handleAction('purge-all')"
                        class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700">
                    Purge All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Selected Modal -->
<div x-show="resetSelectedOpen"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800"
         @click.outside="handleAction('hide-reset-selected-modal')">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset Selected Groups</h3>
            <p class="text-sm text-orange-600 dark:text-orange-400 mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to reset <span x-text="selectedGroupNames.length">0</span> selected group(s)?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will reset the article pointers for the selected groups back to their current state.
            </p>
            <div class="max-h-32 overflow-y-auto mb-4 text-xs text-gray-500 dark:text-gray-400">
                <template x-for="name in selectedGroupNames" :key="name">
                    <div x-text="name"></div>
                </template>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button"
                        @click="handleAction('hide-reset-selected-modal')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        @click="handleAction('reset-selected')"
                        class="px-4 py-2 bg-orange-600 dark:bg-orange-700 text-white rounded-lg hover:bg-orange-700">
                    Reset Selected
                </button>
            </div>
        </div>
    </div>
</div>
</div>

@endsection
