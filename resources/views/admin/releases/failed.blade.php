@extends('layouts.admin')

@section('content')
<div x-data="adminReleaseList" class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-exclamation-triangle">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/release-list')" tone="gray" icon="fas fa-list">View All Releases</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>




        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ url('/admin/failrel-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="failrelsearch"
                               value="{{ request('failrelsearch') }}"
                               placeholder="Search by name or category..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                        Search
                    </button>
                    @if(request('failrelsearch'))
                        <a href="{{ url('/admin/failrel-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Failed Releases List Table -->
        @if($releaselist && $releaselist->count() > 0)
            <x-admin.data-table>
                <x-slot:head>
                            <x-admin.th>Name</x-admin.th>
                            <x-admin.th>Category</x-admin.th>
                            <x-admin.th align="right">Size</x-admin.th>
                            <x-admin.th align="center">Files</x-admin.th>
                            <x-admin.th>Add Date</x-admin.th>
                            <x-admin.th>Post Date</x-admin.th>
                            <x-admin.th align="center">Grabs</x-admin.th>
                            <x-admin.th align="right">Actions</x-admin.th>
                </x-slot:head>

                        @foreach($releaselist as $release)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <a href="{{ url('/admin/release-edit?id=' . $release->guid) }}"
                                       class="text-sm font-medium text-link dark:text-primary-400 hover:underline truncate block max-w-xs"
                                       title="{{ $release->searchname }}">
                                        {{ \Illuminate\Support\Str::limit($release->searchname, 50) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-admin.badge tone="info">{{ $release->category_name ?? 'Unknown' }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <x-admin.badge tone="primary">{{ human_filesize($release->size) }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <x-admin.badge tone="info" icon="fas fa-file">{{ $release->totalpart ?? 0 }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fa-regular fa-calendar-plus text-gray-400 mr-2"></i>
                                        <span title="{{ $release->adddate }}">
                                            {{ userDate($release->adddate, 'Y-m-d H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <i aria-hidden="true" class="fas fa-calendar text-gray-400 mr-2"></i>
                                        <span title="{{ $release->postdate }}">
                                            {{ userDate($release->postdate, 'Y-m-d H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <x-admin.badge tone="success" icon="fas fa-download">{{ $release->grabs ?? 0 }}</x-admin.badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a aria-label="Edit release" href="{{ url('/admin/release-edit?id=' . $release->guid) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                           title="Edit release">
                                            <i aria-hidden="true" class="fas fa-pencil"></i>
                                        </a>
                                        @if($release->guid)
                                            <a aria-label="View release" href="{{ url('/details/' . $release->guid) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300"
                                               title="View release">
                                                <i aria-hidden="true" class="fas fa-eye"></i>
                                            </a>
                                        @endif
                                        <button aria-label="Delete release" type="button"
                                               class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
                                                @click="deleteRelease($event)"
                                               data-delete-release="{{ $release->guid }}"
                                               data-delete-url="{{ url('/admin/release-delete/' . $release->guid) }}"
                                               title="Delete release">
                                            <i aria-hidden="true" class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-admin.data-table>

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
            @php
                $failedEmptyMessage = request('failrelsearch') ? 'No failed releases found matching "'.request('failrelsearch').'".' : 'No failed releases found. Great job!';
            @endphp
            <x-admin.empty-state icon="fas fa-check-circle" title="No failed releases found" :message="$failedEmptyMessage" />
        @endif
    </x-admin.card>
</div>
@endsection
