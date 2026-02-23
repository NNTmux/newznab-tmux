@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-unlink mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ route('admin.show-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            @if($success)
                <div class="bg-green-50 dark:bg-green-900 border-l-4 border-green-500 dark:border-green-600 p-4 rounded mb-6">
                    <div class="flex">
                        <div class="shrink-0">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                                Successfully Removed Video IDs
                            </h3>
                            <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                <p>Video ID <strong class="font-mono">{{ $videoid }}</strong> and its associated episode IDs have been successfully removed from all releases.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        What was removed?
                    </h3>
                    <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 dark:text-green-400 mt-1 mr-2"></i>
                            <span>All releases linked to video ID <strong class="font-mono text-gray-900 dark:text-gray-200">{{ $videoid }}</strong> have been unlinked</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-600 dark:text-green-400 mt-1 mr-2"></i>
                            <span>All episode IDs associated with this video have been removed from releases</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-1 mr-2"></i>
                            <span>The TV show data itself remains in the database and can be re-linked if needed</span>
                        </li>
                    </ul>
                </div>
            @else
                <div class="bg-red-50 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-600 p-4 rounded mb-6">
                    <div class="flex">
                        <div class="shrink-0">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                Operation Failed
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <p>Failed to remove video IDs from releases. This could be due to:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Invalid video ID provided</li>
                                    <li>No releases linked to this video ID</li>
                                    <li>Database error</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 dark:border-blue-600 p-4 rounded">
                <div class="flex">
                    <div class="shrink-0">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            About This Operation
                        </h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            <p>This operation removes the association between TV show data and releases in your database. It's useful when:</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>Incorrect TV show data has been linked to releases</li>
                                <li>You want to force releases to be re-processed for TV show matching</li>
                                <li>You're cleaning up orphaned or incorrect associations</li>
                            </ul>
                            <p class="mt-2"><strong>Note:</strong> This does not delete the TV show from the database, only unlinks it from releases.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                <a href="{{ route('admin.show-list') }}" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-list mr-2"></i>Return to TV Shows List
                </a>
                @if($videoid)
                    <a href="{{ url('admin/show-edit?id=' . $videoid) }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-eye mr-2"></i>View TV Show Details
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

