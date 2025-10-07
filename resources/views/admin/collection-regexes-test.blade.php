@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-flask mr-2"></i>{{ $title ?? 'Collection Regex Test' }}
                </h1>
                <a href="{{ url('/admin/collection_regexes-list') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fa fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
            <div class="flex">
                <i class="fa fa-info-circle text-blue-500 text-xl mr-3"></i>
                <p class="text-sm text-blue-700">
                    Test your collection regex patterns against actual binary data from your database.
                </p>
            </div>
        </div>

        <!-- Test Form -->
        <form method="GET" action="{{ url('/admin/collection_regexes-test') }}" class="px-6 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Group Field -->
                <div>
                    <label for="group" class="block text-sm font-medium text-gray-700 mb-2">
                        Group: <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="group"
                           name="group"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group }}"
                           placeholder="alt.binaries.teevee"
                           required>
                    <p class="mt-2 text-sm text-gray-500">
                        Enter a newsgroup name to test against
                    </p>
                </div>

                <!-- Limit Field -->
                <div>
                    <label for="limit" class="block text-sm font-medium text-gray-700 mb-2">
                        Limit:
                    </label>
                    <input type="number"
                           id="limit"
                           name="limit"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                <label for="regex" class="block text-sm font-medium text-gray-700 mb-2">
                    Regex: <span class="text-red-500">*</span>
                </label>
                <textarea id="regex"
                          name="regex"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                          rows="4"
                          required
                          placeholder="/^(?P<name>.*?)([\. ]S\d{1,3}[\. ]?E\d{1,3})/i">{{ $regex }}</textarea>
                <p class="mt-2 text-sm text-gray-500">
                    Enter the regex pattern to test. Include delimiters and flags.
                </p>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fa fa-play mr-2"></i>Test Regex
            </button>
        </form>

        <!-- Results Section -->
        @if($data)
            <div class="px-6 py-6 border-t border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fa fa-chart-bar mr-2"></i>Test Results:
                </h2>

                @if(count($data) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Binary ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-64">Match</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($data as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            {{ $row['binaryID'] ?? $row['id'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="max-w-2xl break-words">
                                                {{ $row['subject'] ?? '' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            @if(isset($row['match']) && $row['match'])
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                    <i class="fa fa-check mr-1"></i>Match
                                                </span>
                                                @if(isset($row['name']))
                                                    <div class="mt-2 text-xs text-gray-600">
                                                        <strong>Name:</strong> {{ $row['name'] }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                    <i class="fa fa-times mr-1"></i>No Match
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Success Summary -->
                    <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex">
                            <i class="fa fa-check-circle text-green-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-green-800 font-medium">
                                    Tested {{ count($data) }} binaries
                                </p>
                                <p class="text-sm text-green-700 mt-1">
                                    Review the matches above to verify your regex pattern is working correctly.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Results Warning -->
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex">
                            <i class="fa fa-exclamation-triangle text-yellow-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-yellow-800 font-medium">No binaries found</p>
                                <p class="text-sm text-yellow-700 mt-1">
                                    No binaries found for the specified group or no matches found. Try a different group or regex pattern.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
