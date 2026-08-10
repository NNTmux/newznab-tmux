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
        <div class="px-6 py-4 bg-primary-50 dark:bg-primary-900/20 border-b border-primary-100 dark:border-primary-900">
            <div class="flex">
                <i class="fas fa-info-circle text-primary-500 dark:text-primary-400 text-xl mr-3"></i>
                <p class="text-sm text-primary-700 dark:text-primary-300">
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
            $searchTerm = $groupname ?? '';
            $hasRows = $grouplist->count() > 0;
        @endphp

        @if($hasRows || $searchTerm !== '')
            <x-admin.action-bar>
                <div class="flex w-full flex-col gap-3 lg:flex-row lg:flex-nowrap lg:items-end lg:gap-3">
                    <!-- Search: capped at a quarter of the bar so selection never resizes it -->
                    <form name="groupsearch" method="GET" class="w-full lg:w-auto lg:shrink-0 lg:grow-0 lg:basis-1/4">
                        <x-label for="groupname">Search groups</x-label>
                        <div class="flex items-center gap-2">
                            <x-input id="groupname"
                                     name="groupname"
                                     :value="$searchTerm"
                                     class="min-w-0 flex-1"
                                     placeholder="Search for group..." />
                            <x-admin.button type="submit"
                                            icon="fas fa-search"
                                            class="shrink-0"
                                            title="Search groups"
                                            aria-label="Search groups" />
                        </div>
                    </form>

                    <!-- Selection-scoped action: only present while rows are selected -->
                    <x-admin.button type="button"
                                    x-show="hasSelection"
                                    x-cloak
                                    @click="handleAction('show-reset-selected-modal')"
                                    tone="warning"
                                    icon="fas fa-refresh"
                                    class="self-start lg:self-auto lg:shrink-0">
                        Reset <span x-text="selectedCount">0</span> selected
                    </x-admin.button>

                    <div class="flex flex-wrap items-center gap-3 lg:ml-auto lg:flex-nowrap lg:justify-end">
                        <p class="whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($grouplist->total()) }}</span> groups
                            @if($hasRows)
                                <span aria-hidden="true">&middot;</span> Page {{ $grouplist->currentPage() }}/{{ max($grouplist->lastPage(), 1) }}
                            @endif
                        </p>

                        <!-- All-record maintenance actions, kept out of the routine flow -->
                        <div class="relative"
                             @click.outside="closeMaintenance()"
                             @keydown.escape.window="dismissMaintenance()">
                            <x-admin.button type="button"
                                            id="group-maintenance-toggle"
                                            tone="gray"
                                            icon="fas fa-screwdriver-wrench"
                                            @click="toggleMaintenance()"
                                            aria-haspopup="true"
                                            aria-controls="group-maintenance-menu"
                                            x-bind:aria-expanded="maintenanceOpen">
                                Maintenance
                                <i class="fas fa-chevron-down text-xs" aria-hidden="true"></i>
                            </x-admin.button>
                            <div id="group-maintenance-menu"
                                 x-show="maintenanceOpen"
                                 x-cloak
                                 role="group"
                                 aria-label="Maintenance actions for all indexed groups"
                                 class="surface-panel absolute right-0 z-20 mt-2 w-72 rounded-lg border p-3 shadow-lg">
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                                    These actions apply to <strong class="font-semibold text-gray-700 dark:text-gray-300">every indexed group</strong> &mdash; not your selection, and not just this page.
                                </p>
                                <div class="flex flex-col gap-2">
                                    <x-admin.button type="button"
                                                    @click="handleAction('show-reset-modal')"
                                                    tone="warning"
                                                    icon="fas fa-refresh"
                                                    class="w-full">
                                        Reset All
                                    </x-admin.button>
                                    <x-admin.button type="button"
                                                    @click="handleAction('show-purge-modal')"
                                                    tone="danger"
                                                    icon="fas fa-trash"
                                                    class="w-full">
                                        Purge All
                                    </x-admin.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-admin.action-bar>
        @endif

        @if($hasRows)
            <!-- Groups Table -->
            <x-admin.data-table sticky>
                <x-slot:head>
                            <x-admin.th align="center" class="w-12">
                                <input type="checkbox"
                                       id="select-all-groups"
                                       @change="toggleAllCheckboxes()"
                                       class="form-checkbox h-4 w-4 text-primary-600 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:bg-gray-700"
                                       aria-label="Select all groups on this page"
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
                                    <input type="checkbox"
                                           class="group-checkbox form-checkbox h-4 w-4 text-primary-600 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 dark:bg-gray-700"
                                           data-group-id="{{ $group->id }}"
                                           data-group-name="{{ $group->name }}"
                                           @change="onGroupCheckboxChange()">
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ url('/admin/group-edit?id=' . $group->id) }}" class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300">
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
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-primary-100 text-primary-800 hover:bg-primary-200">
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
                                           class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
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

            <!-- Footer: the only numbered paginator on the page -->
            <x-admin.pagination :paginator="$grouplist" :on-each-side="2" />
        @elseif($searchTerm !== '')
            <x-admin.empty-state icon="fas fa-magnifying-glass"
                                 title="No matching groups"
                                 :message="'No groups match “'.$searchTerm.'”. Edit the search above, or clear it to see every group.'">
                <x-admin.button :href="request()->url()" tone="gray" icon="fas fa-xmark">Clear search</x-admin.button>
            </x-admin.empty-state>
        @else
            <x-admin.empty-state icon="fas fa-exclamation-triangle" title="No groups available" message="No groups have been added yet.">
                <x-admin.button :href="url('/admin/group-bulk')" tone="success" icon="fas fa-plus-circle">Add Groups</x-admin.button>
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
                <x-button type="button"
                        variant="muted"
                        @click="handleAction('hide-reset-modal')">
                    Cancel
                </x-button>
                <x-button type="button"
                        variant="warning"
                        @click="handleAction('reset-all')">
                    Reset All
                </x-button>
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
                <x-button type="button"
                        variant="muted"
                        @click="handleAction('hide-purge-modal')">
                    Cancel
                </x-button>
                <x-button type="button"
                        variant="danger"
                        @click="handleAction('purge-all')">
                    Purge All
                </x-button>
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
                <i class="fas fa-exclamation-triangle mr-2"></i>Are you sure you want to reset <span x-text="selectedCount">0</span> selected group(s)?
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
                <x-button type="button"
                        variant="muted"
                        @click="handleAction('hide-reset-selected-modal')">
                    Cancel
                </x-button>
                <x-button type="button"
                        variant="warning"
                        @click="handleAction('reset-selected')">
                    Reset Selected
                </x-button>
            </div>
        </div>
    </div>
</div>
</div>

@endsection
