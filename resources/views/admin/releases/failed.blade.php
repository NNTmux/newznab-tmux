@extends('layouts.admin')

@section('content')
<div x-data="adminReleaseList" class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ $title }}
                </h1>
                <x-button-link href="{{ url('/admin/release-list') }}" variant="secondary" icon="fas fa-list">View All Releases</x-button-link>
            </div>
        </div>

        <!-- Success/Error/Warning Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('/admin/failrel-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="failrelsearch"
                               value="{{ request('failrelsearch') }}"
                               placeholder="Search by name or category..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <x-button type="submit">Search</x-button>
                    @if(request('failrelsearch'))
                        <x-button-link href="{{ url('/admin/failrel-list') }}" variant="secondary">Clear</x-button-link>
                    @endif
                </div>
            </form>
        </div>

        <!-- Failed Releases List Table -->
        @if($releaselist && $releaselist->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Files</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Add Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Post Date</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Grabs</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($releaselist as $release)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <a href="{{ url('/admin/release-edit?id=' . $release->guid) }}"
                                       class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block max-w-xs"
                                       title="{{ $release->searchname }}">
                                        {{ \Illuminate\Support\Str::limit($release->searchname, 50) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                        {{ $release->category_name ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                        {{ human_filesize($release->size) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="{{ url('/admin/release-files?id=' . $release->guid) }}"
                                       class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 hover:bg-indigo-200 dark:hover:bg-indigo-800">
                                        <i class="fas fa-file mr-1"></i>{{ $release->totalpart ?? 0 }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-plus-o text-gray-400 mr-2"></i>
                                        <span title="{{ $release->adddate }}">
                                            {{ userDate($release->adddate, 'Y-m-d H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar text-gray-400 mr-2"></i>
                                        <span title="{{ $release->postdate }}">
                                            {{ userDate($release->postdate, 'Y-m-d H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                        <i class="fas fa-download mr-1"></i>{{ $release->grabs ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ url('/admin/release-edit?id=' . $release->guid) }}"
                                           class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit release">
                                            <i class="fas fa-pencil"></i>
                                        </a>
                                        @if($release->guid)
                                            <a href="{{ url('/details/' . $release->guid) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300"
                                               title="View release">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @endif
                                        <button type="button"
                                               class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                @click="deleteRelease($event)"
                                               data-delete-release="{{ $release->guid }}"
                                               data-delete-url="{{ url('/admin/release-delete/' . $release->guid) }}"
                                               title="Delete release">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-semibold">{{ $releaselist->firstItem() }}</span> to
                        <span class="font-semibold">{{ $releaselist->lastItem() }}</span> of
                        <span class="font-semibold">{{ $releaselist->total() }}</span> failed releases
                    </div>
                    <div class="overflow-x-auto">
                        {{ $releaselist->onEachSide(2)->links() }}
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fas fa-check-circle text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400 text-lg">
                    @if(request('failrelsearch'))
                        No failed releases found matching "{{ request('failrelsearch') }}".
                    @else
                        No failed releases found. Great job!
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
@endsection

