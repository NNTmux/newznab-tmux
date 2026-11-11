@extends('layouts.admin')

@section('content')
<div x-data="adminGroups" class="space-y-6" data-ajax-url="{{ url('/admin/ajax') }}" data-csrf-token="{{ csrf_token() }}">
    <x-admin.page-header :title="$title ?? 'Group List'" icon="fas fa-users" subtitle="Activate, backfill, reset, and purge indexed Usenet groups.">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/group-list-active')" icon="fas fa-check-circle">Active</x-admin.button>
            <x-admin.button :href="url('/admin/group-list-inactive')" tone="gray" icon="fas fa-times-circle">Inactive</x-admin.button>
            <x-admin.button :href="url('/admin/group-list')" tone="gray" icon="fas fa-list">All</x-admin.button>
            <x-admin.button :href="url('/admin/group-bulk')" tone="success" icon="fas fa-plus-circle">Bulk Add</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-info-50 dark:bg-info-900/20 border-b border-info-100 dark:border-info-900">
            <div class="flex">
                <i aria-hidden="true" class="fas fa-info-circle text-info-500 dark:text-info-400 text-xl mr-3"></i>
                <p class="text-sm text-info-700 dark:text-info-300">
                    Below is a list of all usenet groups available to be indexed. Click 'Activate' to start indexing a group.
                    Backfill works independently of active.
                </p>
            </div>
        </div>

        @if(isset($msg) && $msg != '')
            <div class="mx-6 mt-4 p-4 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-900 rounded-lg" id="message">
                <p class="text-success-800 dark:text-success-300">{{ $msg }}</p>
            </div>
        @endif

        @if($grouplist && $grouplist->count() > 0)
            <x-admin.action-bar>
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(20rem,1fr)_minmax(0,auto)_auto]">
                    <!-- Search Form -->
                    <div class="min-w-0">
                        <form name="groupsearch" method="GET">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input id="groupname"
                                           type="text"
                                           name="groupname"
                                           value="{{ $groupname ?? '' }}"
                                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                           placeholder="Search for group...">
                                </div>
                                <x-admin.button type="submit" icon="fas fa-search">Go</x-admin.button>
                            </div>
                        </form>
                    </div>

                    <!-- Pagination -->
                    <div class="min-w-0 overflow-x-auto">
                        <div class="flex justify-center">
                            {{ $grouplist->onEachSide(2)->links() }}
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="flex min-w-0 justify-start xl:justify-end">
                        <div class="flex flex-wrap gap-2 items-center justify-start xl:justify-end">
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
                                <input aria-label="Select all groups on this page" type="checkbox"
                                       id="select-all-groups"
                                       x-model="allChecked"
                                       @change="toggleAllCheckboxes()"
                                       class="form-checkbox h-4 w-4 text-link border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:bg-gray-700"
                                       title="Select all groups on this page">
                            </x-admin.th>
                            <x-admin.th>Group</x-admin.th>
                            <x-admin.th>First Post</x-admin.th>
                            <x-admin.th>Last Post</x-admin.th>
                            <x-admin.th>Last Updated</x-admin.th>
                            <x-admin.th align="center" class="w-32">Status</x-admin.th>
                            <x-admin.th align="center" class="w-32">Backfill</x-admin.th>
                            <x-admin.th align="center" class="w-24">Releases</x-admin.th>
                            <x-admin.th align="center" class="w-24">Min Files</x-admin.th>
                            <x-admin.th align="center" class="w-24">Min Size</x-admin.th>
                            <x-admin.th align="center" class="w-32">Backfill Days</x-admin.th>
                            <x-admin.th align="center" class="w-40">Actions</x-admin.th>
                </x-slot:head>
                        @foreach($grouplist as $group)
                            <tr id="grouprow-{{ $group->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700 group-row">
                                <td class="px-4 py-4 text-center">
                                    <input aria-label="Select item" type="checkbox"
                                           class="group-checkbox form-checkbox h-4 w-4 text-link border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:bg-gray-700"
                                           data-group-id="{{ $group->id }}"
                                           data-group-name="{{ $group->name }}"
                                           @change="onGroupCheckboxChange()">
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ url('/admin/group-edit?id=' . $group->id) }}" class="font-semibold text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300">
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
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-success-100 dark:bg-success-900/40 text-success-800 dark:text-success-200 hover:bg-success-200">
                                            <i aria-hidden="true" class="fas fa-check-circle mr-1"></i>Active
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="handleAction('toggle-group-status', '{{ $group->id }}', '1')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i aria-hidden="true" class="fas fa-times-circle mr-1"></i>Inactive
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center" id="backfill-{{ $group->id }}">
                                    @if($group->backfill == 1)
                                        <button type="button"
                                                @click="handleAction('toggle-backfill', '{{ $group->id }}', '0')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-800 dark:text-primary-200 hover:bg-primary-200">
                                            <i aria-hidden="true" class="fas fa-check-circle mr-1"></i>Enabled
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="handleAction('toggle-backfill', '{{ $group->id }}', '1')"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i aria-hidden="true" class="fas fa-times-circle mr-1"></i>Disabled
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <x-admin.badge tone="neutral">{{ $group->num_releases ?? 0 }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(empty($group->minfilestoformrelease))
                                        <span class="text-gray-400 dark:text-gray-500">n/a</span>
                                    @else
                                        <x-admin.badge tone="neutral">{{ $group->minfilestoformrelease }}</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(empty($group->minsizetoformrelease))
                                        <span class="text-gray-400 dark:text-gray-500">n/a</span>
                                    @else
                                        <x-admin.badge tone="neutral">{{ human_filesize($group->minsizetoformrelease) }}</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <x-admin.badge tone="neutral">{{ $group->backfill_target }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 text-center" id="groupdel-{{ $group->id }}">
                                    <div class="flex gap-1 justify-center">
                                        <a aria-label="Edit this group" href="{{ url('/admin/group-edit?id=' . $group->id) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit this group">
                                            <i aria-hidden="true" class="fas fa-pencil"></i>
                                        </a>
                                        <button aria-label="Reset this group" type="button"
                                                @click="handleAction('reset-group', '{{ $group->id }}')"
                                                class="text-warning-600 dark:text-warning-400 hover:text-warning-900 dark:hover:text-warning-300"
                                                title="Reset this group">
                                            <i aria-hidden="true" class="fas fa-refresh"></i>
                                        </button>
                                        <button aria-label="Delete this group" type="button"
                                                @click="handleAction('delete-group', '{{ $group->id }}')"
                                                class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
                                                title="Delete this group">
                                            <i aria-hidden="true" class="fas fa-trash"></i>
                                        </button>
                                        <button aria-label="Purge this group" type="button"
                                                @click="handleAction('purge-group', '{{ $group->id }}')"
                                                class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
                                                title="Purge this group">
                                            <i aria-hidden="true" class="fas fa-eraser"></i>
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
            <x-admin.empty-state icon="fas fa-exclamation-triangle" title="No groups available" message="No groups have been added yet.">
                <x-admin.button :href="url('/admin/group-bulk')" tone="success" icon="fas fa-plus-circle" class="mt-4">Add Groups</x-admin.button>
            </x-admin.empty-state>
        @endif
    </x-admin.card>

    <!-- Reset All Modal -->
    <x-modal open="resetAllOpen" close="handleAction('hide-reset-modal')" labelledby="groups-modal-title-0">
        <div class="mt-3">
            <h3 id="groups-modal-title-0" class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset All Groups</h3>
            <p class="text-sm text-danger-600 dark:text-danger-400 mb-2">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to reset all groups?
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
                        class="px-4 py-2 bg-warning-600 text-white rounded-lg hover:bg-warning-700">
                    Reset All
                </button>
            </div>
        </div>
    </x-modal>

<!-- Purge All Modal -->
<x-modal open="purgeAllOpen" close="handleAction('hide-purge-modal')" labelledby="groups-modal-title-1">
        <div class="mt-3">
            <h3 id="groups-modal-title-1" class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Purge All Groups</h3>
            <p class="text-sm text-danger-600 dark:text-danger-400 mb-2">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to purge all groups?
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
                        class="px-4 py-2 bg-danger-600 dark:bg-danger-700 text-white rounded-lg hover:bg-danger-700">
                    Purge All
                </button>
            </div>
        </div>
</x-modal>

<!-- Reset Selected Modal -->
<x-modal open="resetSelectedOpen" close="handleAction('hide-reset-selected-modal')" labelledby="groups-modal-title-2">
        <div class="mt-3">
            <h3 id="groups-modal-title-2" class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset Selected Groups</h3>
            <p class="text-sm text-orange-600 dark:text-orange-400 mb-2">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to reset <span x-text="selectedGroupNames.length">0</span> selected group(s)?
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
</x-modal>
</div>

@endsection
