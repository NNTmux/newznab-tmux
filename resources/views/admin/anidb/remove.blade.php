@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-unlink">
        <x-slot:actions>
            <x-admin.button :href="route('admin.anidb-list')" tone="gray" icon="fas fa-arrow-left">Back to List</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Content -->
        <div class="p-6">
            @if($success)
                <div class="bg-success-50 dark:bg-success-900 border-l-4 border-success-500 dark:border-success-600 p-4 rounded mb-6">
                    <div class="flex">
                        <div class="shrink-0">
                            <i aria-hidden="true" class="fas fa-check-circle text-success-600 dark:text-success-400 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-success-800 dark:text-success-200">
                                Successfully Removed AniDB ID
                            </h3>
                            <div class="mt-2 text-sm text-success-700 dark:text-success-300">
                                <p>AniDB ID <strong class="font-mono">{{ $anidbid }}</strong> has been successfully removed from all releases.</p>
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
                            <i aria-hidden="true" class="fas fa-check text-success-600 dark:text-success-400 mt-1 mr-2"></i>
                            <span>All releases linked to AniDB ID <strong class="font-mono text-gray-900 dark:text-gray-200">{{ $anidbid }}</strong> have been unlinked</span>
                        </li>
                        <li class="flex items-start">
                            <i aria-hidden="true" class="fas fa-info-circle text-info-600 dark:text-info-400 mt-1 mr-2"></i>
                            <span>The anime data itself remains in the database and can be re-linked if needed</span>
                        </li>
                        <li class="flex items-start">
                            <i aria-hidden="true" class="fas fa-sync text-purple-600 dark:text-purple-400 mt-1 mr-2"></i>
                            <span>Releases can be re-processed to automatically re-link with correct anime data</span>
                        </li>
                    </ul>
                </div>
            @else
                <div class="bg-danger-50 dark:bg-danger-900 border-l-4 border-danger-500 dark:border-danger-600 p-4 rounded mb-6">
                    <div class="flex">
                        <div class="shrink-0">
                            <i aria-hidden="true" class="fas fa-exclamation-circle text-danger-600 dark:text-danger-400 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">
                                Operation Failed
                            </h3>
                            <div class="mt-2 text-sm text-danger-700 dark:text-danger-300">
                                <p>Failed to remove AniDB ID from releases. This could be due to:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Invalid AniDB ID provided</li>
                                    <li>No releases linked to this AniDB ID</li>
                                    <li>Database error</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Info Box -->
            <div class="mt-6 bg-info-50 dark:bg-info-900 border-l-4 border-info-500 dark:border-info-600 p-4 rounded">
                <div class="flex">
                    <div class="shrink-0">
                        <i aria-hidden="true" class="fas fa-info-circle text-info-600 dark:text-info-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-info-800 dark:text-info-200">
                            About This Operation
                        </h3>
                        <div class="mt-2 text-sm text-info-700 dark:text-info-300">
                            <p>This operation removes the association between anime data from AniDB and releases in your database. It's useful when:</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li>Incorrect anime data has been linked to releases</li>
                                <li>You want to force releases to be re-processed for anime matching</li>
                                <li>You're cleaning up orphaned or incorrect associations</li>
                            </ul>
                            <p class="mt-2"><strong>Note:</strong> This does not delete the anime from the database, only unlinks it from releases.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                <a href="{{ route('admin.anidb-list') }}" class="px-6 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover">
                    <i aria-hidden="true" class="fas fa-list mr-2"></i>Return to AniDB List
                </a>
                @if($anidbid)
                    <a href="{{ url('admin/anidb-edit/' . $anidbid) }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i aria-hidden="true" class="fas fa-eye mr-2"></i>View Anime Details
                    </a>
                @endif
            </div>
        </div>
    </x-admin.card>
</div>
@endsection
