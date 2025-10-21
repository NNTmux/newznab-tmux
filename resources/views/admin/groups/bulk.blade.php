@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-plus-square mr-2"></i>{{ $title ?? 'Bulk Add Newsgroups' }}
                </h1>
                <a href="{{ url('/admin/group-list') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200">
                    <i class="fa fa-list mr-2"></i>View All Groups
                </a>
            </div>
        </div>

        <div class="px-6 py-6">
            @if(!empty($groupmsglist))
                <!-- Success Info -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex">
                        <i class="fa fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <p class="text-blue-700">
                            The following groups have been processed. You can now view them in the group list.
                        </p>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                            @foreach($groupmsglist as $group)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fa fa-users text-gray-400 mr-3"></i>
                                            <span class="font-medium text-gray-900">{{ $group['group'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(strpos($group['msg'], 'Error') !== false)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                <i class="fa fa-exclamation-circle mr-1"></i>{{ $group['msg'] }}
                                            </span>
                                        @elseif(strpos($group['msg'], 'exists') !== false)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                                <i class="fa fa-exclamation-triangle mr-1"></i>{{ $group['msg'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                <i class="fa fa-check-circle mr-1"></i>{{ $group['msg'] }}
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
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex">
                        <i class="fa fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <p class="text-blue-700">
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
                                <i class="fa fa-filter text-gray-400"></i>
                            </div>
                            <textarea id="groupfilter"
                                      name="groupfilter"
                                      class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                      rows="5"
                                      placeholder="e.g. alt.binaries.cd.image.linux|alt.binaries.warez.linux"></textarea>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            A regular expression to match against group names. Separate multiple patterns with the pipe symbol (|).
                            <br>Example: <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs text-pink-600">alt.binaries.cd.image.linux|alt.binaries.warez.linux</code>
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
                                       class="w-4 h-4 text-blue-600 dark:text-blue-400 border-gray-300 dark:border-gray-600 focus:ring-blue-500"
                                       checked>
                                <label for="active_yes" class="ml-2 text-sm text-gray-700">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="active"
                                       id="active_no"
                                       value="0"
                                       class="w-4 h-4 text-blue-600 dark:text-blue-400 border-gray-300 dark:border-gray-600 focus:ring-blue-500">
                                <label for="active_no" class="ml-2 text-sm text-gray-700">No</label>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
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
                                       class="w-4 h-4 text-blue-600 dark:text-blue-400 border-gray-300 dark:border-gray-600 focus:ring-blue-500">
                                <label for="backfill_yes" class="ml-2 text-sm text-gray-700">Yes</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio"
                                       name="backfill"
                                       id="backfill_no"
                                       value="0"
                                       class="w-4 h-4 text-blue-600 dark:text-blue-400 border-gray-300 dark:border-gray-600 focus:ring-blue-500"
                                       checked>
                                <label for="backfill_no" class="ml-2 text-sm text-gray-700">No</label>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            Inactive groups will not have backfill headers downloaded for them.
                        </p>
                    </div>
                </form>
            @endif
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50">
            <div class="flex justify-between">
                <a href="{{ url('/admin/group-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Groups
                </a>
                @if(empty($groupmsglist))
                    <button type="submit" form="groupBulkForm" class="px-4 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700">
                        <i class="fa fa-plus-circle mr-2"></i>Add Groups
                    </button>
                @else
                    <a href="{{ url('/admin/group-bulk') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-plus-circle mr-2"></i>Add More Groups
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

