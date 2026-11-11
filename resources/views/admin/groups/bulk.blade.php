@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Bulk Add Newsgroups'" icon="fas fa-plus-square">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/group-list')" tone="gray" icon="fas fa-list">View All Groups</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <div class="px-6 py-6">
            @if(!empty($groupmsglist))
                <!-- Success Info -->
                <div class="mb-6 p-4 bg-info-50 dark:bg-info-900/30 border border-info-200 dark:border-info-800 rounded-lg">
                    <div class="flex">
                        <i aria-hidden="true" class="fas fa-info-circle text-info-500 dark:text-info-400 text-xl mr-3"></i>
                        <p class="text-info-700 dark:text-info-300">
                            The following groups have been processed. You can now view them in the group list.
                        </p>
                    </div>
                </div>

                <!-- Results Table -->
                <x-admin.data-table>
                    <x-slot:head>
                                <x-admin.th>Group</x-admin.th>
                                <x-admin.th>Status</x-admin.th>
                    </x-slot:head>

                            @foreach($groupmsglist as $group)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i aria-hidden="true" class="fas fa-users text-gray-400 dark:text-gray-500 mr-3"></i>
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $group['group'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(strpos($group['msg'], 'Error') !== false)
                                            <x-admin.badge tone="danger" icon="fas fa-exclamation-circle">{{ $group['msg'] }}</x-admin.badge>
                                        @elseif(strpos($group['msg'], 'exists') !== false)
                                            <x-admin.badge tone="warning" icon="fas fa-exclamation-triangle">{{ $group['msg'] }}</x-admin.badge>
                                        @else
                                            <x-admin.badge tone="success" icon="fas fa-check-circle">{{ $group['msg'] }}</x-admin.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-admin.data-table>
            @else
                <!-- Info Alert -->
                <div class="mb-6 p-4 bg-info-50 dark:bg-info-900/30 border border-info-200 dark:border-info-800 rounded-lg">
                    <div class="flex">
                        <i aria-hidden="true" class="fas fa-info-circle text-info-500 dark:text-info-400 text-xl mr-3"></i>
                        <p class="text-info-700 dark:text-info-300">
                            Enter a regular expression to match multiple groups for bulk addition to the system.
                        </p>
                    </div>
                </div>

                <!-- Form -->
                <form action="{{ url('/admin/group-bulk?action=submit') }}" method="POST" id="groupBulkForm">
                    @csrf
                    <x-form-errors class="mb-6" />

                    <!-- Group Pattern -->
                    <div class="mb-6">
                        <label for="groupfilter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Group Pattern: <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute top-3 left-3 pointer-events-none">
                                <i aria-hidden="true" class="fas fa-filter text-gray-400"></i>
                            </div>
                            <textarea id="groupfilter"
                                      name="groupfilter"
                                      class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                                      rows="5"
                                      placeholder="e.g. alt.binaries.cd.image.linux|alt.binaries.warez.linux"></textarea>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            A regular expression to match against group names. Separate multiple patterns with the pipe symbol (|).
                            <br>Example: <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs text-pink-600 dark:text-pink-400">alt.binaries.cd.image.linux|alt.binaries.warez.linux</code>
                        </p>
                    </div>

                    <!-- Active Status -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Active:</label>
                        <div class="flex items-center gap-6">
                            <div class="flex items-center">
                                <input type="radio"
                                       name="active"
                                       id="active_yes"
                                       value="1"
                                       class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                       checked>
                                <label for="active_yes" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="active"
                                       id="active_no"
                                       value="0"
                                       class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500">
                                <label for="active_no" class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</label>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Inactive groups will not have headers downloaded for them.
                        </p>
                    </div>

                    <!-- Backfill Status -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Backfill:</label>
                        <div class="flex items-center gap-6">
                            <div class="flex items-center">
                                <input type="radio"
                                       name="backfill"
                                       id="backfill_yes"
                                       value="1"
                                       class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500">
                                <label for="backfill_yes" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="backfill"
                                       id="backfill_no"
                                       value="0"
                                       class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                       checked>
                                <label for="backfill_no" class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</label>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Inactive groups will not have backfill headers downloaded for them.
                        </p>
                    </div>
                </form>
            @endif
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
            <div class="flex justify-between">
                <a href="{{ url('/admin/group-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i>Back to Groups
                </a>
                @if(empty($groupmsglist))
                    <button type="submit" form="groupBulkForm" class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white rounded-lg hover:bg-success-700 dark:hover:bg-success-600">
                        <i aria-hidden="true" class="fas fa-plus-circle mr-2"></i>Add Groups
                    </button>
                @else
                    <a href="{{ url('/admin/group-bulk') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-accent">
                        <i aria-hidden="true" class="fas fa-plus-circle mr-2"></i>Add More Groups
                    </a>
                @endif
            </div>
        </div>
    </x-admin.card>
</div>

@endsection
