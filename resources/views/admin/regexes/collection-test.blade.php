@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Collection Regex Test'" icon="fas fa-flask">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/collection_regexes-list')" tone="gray" icon="fas fa-arrow-left">Back to List</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-info-50 dark:bg-info-950/40 border-b border-info-100">
            <div class="flex">
                <i aria-hidden="true" class="fas fa-info-circle text-info-500 text-xl mr-3"></i>
                <p class="text-sm text-info-700 dark:text-info-300">
                    Test your collection regex patterns against actual binary data from your database.
                </p>
            </div>
        </div>

        <!-- Test Form -->
        <form method="GET" action="{{ url('/admin/collection_regexes-test') }}" class="px-6 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Group Field -->
                <div>
                    <label for="group" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Group: <span class="text-danger-500">*</span>
                    </label>
                    <input type="text"
                           id="group"
                           name="group"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           value="{{ $group }}"
                           placeholder="alt.binaries.teevee"
                           required>
                    <p class="mt-2 text-sm text-gray-500">
                        Enter a newsgroup name to test against
                    </p>
                </div>

                <!-- Limit Field -->
                <div>
                    <label for="limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Limit:
                    </label>
                    <input type="number"
                           id="limit"
                           name="limit"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           value="{{ $limit }}"
                           min="1"
                           max="1000">
                    <p class="mt-2 text-sm text-gray-500">
                        Number of binaries to test (max 1000)
                    </p>
                </div>
            </div>

            <!-- Regex Field -->
            <div class="mb-6">
                <label for="regex" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Regex: <span class="text-danger-500">*</span>
                </label>
                <textarea id="regex"
                          name="regex"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                          rows="4"
                          required
                          placeholder="/^(?P<name>.*?)([\. ]S\d{1,3}[\. ]?E\d{1,3})/i">{{ regex_display_value($regex) }}</textarea>
                <p class="mt-2 text-sm text-gray-500">
                    Enter the regex pattern to test. Include delimiters and flags.
                </p>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                <i aria-hidden="true" class="fas fa-play mr-2"></i>Test Regex
            </button>
        </form>

        <!-- Results Section -->
        @if($data)
            <div class="px-6 py-6 border-t border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    <i aria-hidden="true" class="fas fa-chart-bar mr-2"></i>Test Results:
                </h2>

                @if(count($data) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-admin.th width="24">Binary ID</x-admin.th>
                                    <x-admin.th>Subject</x-admin.th>
                                    <x-admin.th width="64">Match</x-admin.th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($data as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $row['binaryID'] ?? $row['id'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="max-w-2xl wrap-break-word">
                                                {{ $row['subject'] ?? '' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            @if(isset($row['match']) && $row['match'])
                                                <x-admin.badge tone="success" icon="fas fa-check">Match</x-admin.badge>
                                                @if(isset($row['name']))
                                                    <div class="mt-2 text-xs text-gray-600">
                                                        <strong>Name:</strong> {{ $row['name'] }}
                                                    </div>
                                                @endif
                                            @else
                                                <x-admin.badge tone="danger" icon="fas fa-times">No Match</x-admin.badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Success Summary -->
                    <div class="mt-6 p-4 bg-success-50 dark:bg-success-950/40 border border-success-200 rounded-lg">
                        <div class="flex">
                            <i aria-hidden="true" class="fas fa-check-circle text-success-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-success-800 dark:text-success-200 font-medium">
                                    Tested {{ count($data) }} binaries
                                </p>
                                <p class="text-sm text-success-700 dark:text-success-300 mt-1">
                                    Review the matches above to verify your regex pattern is working correctly.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Results Warning -->
                    <div class="p-4 bg-warning-50 dark:bg-warning-950/40 border border-warning-200 rounded-lg">
                        <div class="flex">
                            <i aria-hidden="true" class="fas fa-exclamation-triangle text-warning-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-warning-800 dark:text-warning-200 font-medium">No binaries found</p>
                                <p class="text-sm text-warning-700 dark:text-warning-300 mt-1">
                                    No binaries found for the specified group or no matches found. Try a different group or regex pattern.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-admin.card>
</div>
@endsection
