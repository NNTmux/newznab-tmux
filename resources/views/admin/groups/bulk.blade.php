@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    <i class="fas fa-plus-square mr-2"></i>{{ $title ?? 'Bulk Add Newsgroups' }}
                </h1>
                <x-button-link href="{{ url('/admin/group-list') }}" variant="muted" icon="fas fa-list">
                    View All Groups
                </x-button-link>
            </div>
        </div>

        <div class="px-6 py-6">
            @if(!empty($groupmsglist))
                <!-- Success Info -->
                <div class="mb-6 p-4 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-info-circle text-primary-500 dark:text-primary-400 text-xl mr-3"></i>
                        <p class="text-primary-700 dark:text-primary-300">
                            The following groups have been processed. You can now view them in the group list.
                        </p>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Group</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($groupmsglist as $group)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-users text-gray-400 dark:text-gray-500 mr-3"></i>
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $group['group'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(strpos($group['msg'], 'Error') !== false)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $group['msg'] }}
                                            </span>
                                        @elseif(strpos($group['msg'], 'exists') !== false)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>{{ $group['msg'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                                <i class="fas fa-check-circle mr-1"></i>{{ $group['msg'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Info Alert -->
                <div class="mb-6 p-4 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-info-circle text-primary-500 dark:text-primary-400 text-xl mr-3"></i>
                        <p class="text-primary-700 dark:text-primary-300">
                            Enter a regular expression to match multiple groups for bulk addition to the system.
                        </p>
                    </div>
                </div>

                <!-- Form -->
                <form action="{{ url('/admin/group-bulk?action=submit') }}" method="POST" id="groupBulkForm">
                    @csrf

                    <!-- Group Pattern -->
                    <div class="mb-6">
                        <label for="groupfilter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Group Pattern: <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute top-3 left-3 pointer-events-none">
                                <i class="fas fa-filter text-gray-400"></i>
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
                                       class="w-4 h-4 text-primary-600 dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                       checked>
                                <label for="active_yes" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="active"
                                       id="active_no"
                                       value="0"
                                       class="w-4 h-4 text-primary-600 dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500">
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
                                       class="w-4 h-4 text-primary-600 dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500">
                                <label for="backfill_yes" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="backfill"
                                       id="backfill_no"
                                       value="0"
                                       class="w-4 h-4 text-primary-600 dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
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
                <x-button-link href="{{ url('/admin/group-list') }}" variant="muted" icon="fas fa-arrow-left">
                    Back to Groups
                </x-button-link>
                @if(empty($groupmsglist))
                    <x-button type="submit" form="groupBulkForm" variant="success" icon="fas fa-plus-circle">
                        Add Groups
                    </x-button>
                @else
                    <x-button-link href="{{ url('/admin/group-bulk') }}" icon="fas fa-plus-circle">
                        Add More Groups
                    </x-button-link>
                @endif
            </div>
        </div>
    </x-admin.card>
</div>

@endsection

