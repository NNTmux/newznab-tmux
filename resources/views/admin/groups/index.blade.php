@extends('layouts.admin')

@section('content')
<div class="max-w-full px-4 py-6" data-ajax-url="{{ url('/admin/ajax') }}" data-csrf-token="{{ csrf_token() }}">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fa fa-users mr-2"></i>{{ $title ?? 'Group List' }}
                </h1>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ url('/admin/group-list-active') }}" class="px-3 py-2 bg-blue-600 dark:bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-700">
                        <i class="fa fa-check-circle mr-1"></i>Active Groups
                    </a>
                    <a href="{{ url('/admin/group-list-inactive') }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700">
                        <i class="fa fa-times-circle mr-1"></i>Inactive Groups
                    </a>
                    <a href="{{ url('/admin/group-list') }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        <i class="fa fa-list mr-1"></i>All Groups
                    </a>
                    <a href="{{ url('/admin/group-bulk') }}" class="px-3 py-2 bg-green-600 dark:bg-green-700 text-white text-sm rounded-lg hover:bg-green-700">
                        <i class="fa fa-plus-circle mr-1"></i>Bulk Add
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-900">
            <div class="flex">
                <i class="fa fa-info-circle text-blue-500 dark:text-blue-400 text-xl mr-3"></i>
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

        @if($grouplist && $grouplist->count() > 0)
            <!-- Search and Actions Bar -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Search Form -->
                    <div>
                        <form name="groupsearch" method="GET">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa fa-search text-gray-400"></i>
                                    </div>
                                    <input id="groupname"
                                           type="text"
                                           name="groupname"
                                           value="{{ $groupname ?? '' }}"
                                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                           placeholder="Search for group...">
                                </div>
                                <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                                    Go
                                </button>
                            </div>
                        </form>
                    </div>

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
                            <button type="button"
                                    id="reset-selected-btn"
                                    data-action="show-reset-selected-modal"
                                    class="hidden px-3 py-2 bg-orange-600 dark:bg-orange-700 text-white text-sm rounded-lg hover:bg-orange-700 dark:hover:bg-orange-600">
                                <i class="fa fa-refresh mr-1"></i> Reset Selected
                            </button>
                            <button type="button"
                                    data-action="show-reset-modal"
                                    class="px-3 py-2 bg-yellow-600 text-white text-sm rounded-lg hover:bg-yellow-700">
                                <i class="fa fa-refresh mr-1"></i> Reset All
                            </button>
                            <button type="button"
                                    data-action="show-purge-modal"
                                    class="px-3 py-2 bg-red-600 dark:bg-red-700 text-white text-sm rounded-lg hover:bg-red-700">
                                <i class="fa fa-trash mr-1"></i> Purge All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Groups Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                                <input type="checkbox"
                                       id="select-all-groups"
                                       data-action="select-all-groups"
                                       class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                       title="Select all groups on this page">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">First Post</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Post</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Updated</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Backfill</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Releases</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Min Files</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Min Size</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Backfill Days</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-40">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($grouplist as $group)
                            <tr id="grouprow-{{ $group->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700 group-row">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox"
                                           class="group-checkbox form-checkbox h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700"
                                           data-group-id="{{ $group->id }}"
                                           data-group-name="{{ $group->name }}">
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
                                                data-action="toggle-group-status"
                                                data-group-id="{{ $group->id }}"
                                                data-status="0"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 hover:bg-green-200">
                                            <i class="fa fa-check-circle mr-1"></i>Active
                                        </button>
                                    @else
                                        <button type="button"
                                                data-action="toggle-group-status"
                                                data-group-id="{{ $group->id }}"
                                                data-status="1"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i class="fa fa-times-circle mr-1"></i>Inactive
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center" id="backfill-{{ $group->id }}">
                                    @if($group->backfill == 1)
                                        <button type="button"
                                                data-action="toggle-backfill"
                                                data-group-id="{{ $group->id }}"
                                                data-status="0"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200">
                                            <i class="fa fa-check-circle mr-1"></i>Enabled
                                        </button>
                                    @else
                                        <button type="button"
                                                data-action="toggle-backfill"
                                                data-group-id="{{ $group->id }}"
                                                data-status="1"
                                                class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                                            <i class="fa fa-times-circle mr-1"></i>Disabled
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
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <button type="button"
                                                data-action="reset-group"
                                                data-group-id="{{ $group->id }}"
                                                class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300"
                                                title="Reset this group">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                        <button type="button"
                                                data-action="delete-group"
                                                data-group-id="{{ $group->id }}"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                title="Delete this group">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <button type="button"
                                                data-action="purge-group"
                                                data-group-id="{{ $group->id }}"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                title="Purge this group">
                                            <i class="fa fa-eraser"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

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
            <div class="px-6 py-12 text-center">
                <i class="fa fa-exclamation-triangle text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No groups available</h3>
                <p class="text-gray-500 mb-4">No groups have been added yet.</p>
                <a href="{{ url('/admin/group-bulk') }}" class="inline-flex items-center px-4 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700">
                    <i class="fa fa-plus-circle mr-2"></i>Add Groups
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Reset All Modal -->
<div id="resetAllModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset All Groups</h3>
            <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>Are you sure you want to reset all groups?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will reset the article pointers for all groups back to their current state.
            </p>
            <div class="flex justify-end gap-3">
                <button type="button"
                        data-action="hide-reset-modal"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        data-action="reset-all"
                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    Reset All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Purge All Modal -->
<div id="purgeAllModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Purge All Groups</h3>
            <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>Are you sure you want to purge all groups?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will delete all releases and binaries for all groups. This action cannot be undone!
            </p>
            <div class="flex justify-end gap-3">
                <button type="button"
                        data-action="hide-purge-modal"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        data-action="purge-all"
                        class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700">
                    Purge All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Selected Modal -->
<div id="resetSelectedModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Reset Selected Groups</h3>
            <p class="text-sm text-orange-600 dark:text-orange-400 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>Are you sure you want to reset <span id="reset-selected-count">0</span> selected group(s)?
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                This will reset the article pointers for the selected groups back to their current state.
            </p>
            <div id="reset-selected-list" class="max-h-32 overflow-y-auto mb-4 text-xs text-gray-500 dark:text-gray-400"></div>
            <div class="flex justify-end gap-3">
                <button type="button"
                        data-action="hide-reset-selected-modal"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="button"
                        data-action="reset-selected"
                        class="px-4 py-2 bg-orange-600 dark:bg-orange-700 text-white rounded-lg hover:bg-orange-700">
                    Reset Selected
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

