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
                                    id="edit-selected-groups"
                                    x-show="hasSelection"
                                    x-cloak
                                    @click="handleAction('show-edit-selected-modal')"
                                    icon="fas fa-pen-to-square"
                                    class="self-start lg:self-auto lg:shrink-0">
                        Edit <span x-text="selectedCount">0</span> selected
                    </x-admin.button>

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
                            @include('admin.groups._row', ['group' => $group])
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

    <!-- Edit Selected Modal -->
    <div x-show="editSelectedOpen"
         x-cloak
         @keydown.escape.window="handleAction('hide-edit-selected-modal')"
         class="fixed inset-0 z-50 h-full w-full overflow-y-auto bg-gray-600/50">
        <div class="surface-panel relative top-8 mx-auto w-full max-w-2xl rounded-lg border p-6 shadow-lg"
             role="dialog"
             aria-modal="true"
             aria-labelledby="edit-selected-title"
             @click.outside="handleAction('hide-edit-selected-modal')">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h3 id="edit-selected-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit Selected Groups</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Change settings for <span x-text="selectedCount">0</span> selected groups. Empty fields are left unchanged.
                    </p>
                </div>
                <button type="button"
                        class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100"
                        aria-label="Close Edit Selected dialog"
                        @click="handleAction('hide-edit-selected-modal')">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div x-show="editSelectedEditing" class="space-y-4">
                <div>
                    <x-label for="edit-selected-backfill-target">Backfill Days</x-label>
                    <x-input id="edit-selected-backfill-target"
                             type="text"
                             inputmode="numeric"
                             placeholder="Mixed — leave unchanged"
                             x-model="editBackfillTarget"
                             @input="validateEditSelected()" />
                    <p x-show="editBackfillTargetError" x-text="editBackfillTargetError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                </div>

                <div>
                    <x-label for="edit-selected-min-files">Minimum Files to Form Release</x-label>
                    <x-input id="edit-selected-min-files"
                             type="text"
                             inputmode="numeric"
                             placeholder="Mixed — leave unchanged"
                             x-model="editMinFiles"
                             @input="validateEditSelected()" />
                    <p x-show="editMinFilesError" x-text="editMinFilesError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                </div>

                <div>
                    <x-label for="edit-selected-min-size">Minimum File Size</x-label>
                    <x-input id="edit-selected-min-size"
                             type="text"
                             placeholder="100M, 2.5G, or bytes"
                             x-model="editMinSize"
                             @input="validateEditSelected()" />
                    <p x-show="editMinSizeReadout" x-text="editMinSizeReadout" class="mt-1 text-sm text-gray-600 dark:text-gray-400"></p>
                    <p x-show="editMinSizeError" x-text="editMinSizeError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-label for="edit-selected-active">Active</x-label>
                        <x-select id="edit-selected-active" x-model="editActive" @change="validateEditSelected()">
                            <option value="">No change</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </x-select>
                    </div>
                    <div>
                        <x-label for="edit-selected-backfill">Backfill</x-label>
                        <x-select id="edit-selected-backfill" x-model="editBackfill" @change="validateEditSelected()">
                            <option value="">No change</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </x-select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3">
                    <x-button type="button" variant="muted" @click="handleAction('hide-edit-selected-modal')">Cancel</x-button>
                    <x-button type="button"
                              variant="primary"
                              x-bind:disabled="editSaveDisabled"
                              @click="handleAction('confirm-edit-selected')">
                        Save
                    </x-button>
                </div>
            </div>

            <div x-show="editSelectedConfirming" class="space-y-4">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Apply these changes to <strong x-text="selectedCount">0</strong> selected groups?
                </p>
                <dl class="surface-panel-alt space-y-2 rounded-lg border p-4">
                    <template x-for="change in editConfirmationChanges" :key="change.key">
                        <div class="flex justify-between gap-4 text-sm">
                            <dt x-text="change.label" class="text-gray-600 dark:text-gray-400"></dt>
                            <dd x-text="change.value" class="font-medium text-gray-900 dark:text-gray-100"></dd>
                        </div>
                    </template>
                </dl>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Affected groups include</p>
                    <template x-for="name in editConfirmationGroupNames" :key="name">
                        <div x-text="name" class="text-sm text-gray-700 dark:text-gray-300"></div>
                    </template>
                </div>
                <div class="flex justify-end gap-3 pt-3">
                    <x-button type="button" variant="muted" @click="handleAction('back-to-edit-selected')">Back</x-button>
                    <x-button type="button" variant="primary" @click="handleAction('save-edit-selected')">Apply Changes</x-button>
                </div>
            </div>
        </div>
    </div>

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
