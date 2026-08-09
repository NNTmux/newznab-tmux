@extends('layouts.admin')

@section('content')
<div x-data="adminReleaseList" class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-list" />

        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-800 dark:text-green-200 text-sm" role="alert">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-200 text-sm" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        <x-admin.filter-panel>
            <form method="GET" action="{{ route('admin.release-list') }}" class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400" aria-hidden="true"></i>
                    </div>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Search by release name..."
                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <select name="category_id"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white sm:min-w-[220px]">
                    @foreach($catlist as $catId => $catTitle)
                        <option value="{{ $catId }}" {{ (int) request('category_id', -1) === (int) $catId ? 'selected' : '' }}>
                            {{ $catTitle }}
                        </option>
                    @endforeach
                </select>
                <x-admin.button type="submit" icon="fas fa-filter">
                    Apply
                </x-admin.button>
                @if(request('search') || (request()->filled('category_id') && (int) request('category_id') !== -1))
                    <x-admin.button :href="route('admin.release-list')" tone="gray" icon="fas fa-eraser">
                        Clear
                    </x-admin.button>
                @endif
            </form>
        </x-admin.filter-panel>

        @if($releaselist->count() > 0)
            <form id="bulk-category-form"
                  method="POST"
                  action="{{ route('admin.release-bulk-category') }}"
                  @submit="validateBulkAction($event)">
                @csrf
                <x-admin.action-bar>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.button type="button"
                                @click="selectAll()"
                                tone="gray"
                                icon="fas fa-check-square">
                            Select All
                        </x-admin.button>
                        <x-admin.button type="button"
                                @click="clearSelection()"
                                tone="gray"
                                icon="fas fa-square">
                            Clear Selection
                        </x-admin.button>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            <span x-text="selectedCount"></span> selected
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <select name="categories_id"
                                class="text-sm border-gray-300 dark:border-gray-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-gray-200 min-w-[200px]">
                            @foreach($catlist as $catId => $catTitle)
                                @if((int) $catId > 0)
                                    <option value="{{ $catId }}">{{ $catTitle }}</option>
                                @endif
                            @endforeach
                        </select>
                        <x-admin.button type="submit" tone="success" icon="fas fa-tags">Change Category</x-admin.button>
                    </div>
                </x-admin.action-bar>
            </form>

            <x-admin.data-table sticky>
                <x-slot:head>
                    <x-admin.th class="w-10">
                        <span class="sr-only">Select</span>
                    </x-admin.th>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Name</x-admin.th>
                    <x-admin.th>Category</x-admin.th>
                    <x-admin.th>Size</x-admin.th>
                    <x-admin.th>Files</x-admin.th>
                    <x-admin.th>Added</x-admin.th>
                    <x-admin.th>Posted</x-admin.th>
                    <x-admin.th>Grabs</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                @foreach($releaselist as $release)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox"
                                   form="bulk-category-form"
                                   name="guids[]"
                                   value="{{ $release->guid }}"
                                   @change="onCheckboxChange()"
                                   class="release-checkbox rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:bg-gray-700">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $release->id }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-900 dark:text-gray-100 font-medium max-w-md wrap-break-word break-all" title="{{ $release->searchname }}">
                                {{ $release->searchname }}
                            </div>
                            @if($release->name && $release->name !== $release->searchname)
                                <div class="text-gray-500 dark:text-gray-400 text-xs mt-1 max-w-md truncate" title="{{ $release->name }}">
                                    {{ $release->name }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 dark:bg-primary-900/20 text-primary-800 dark:text-primary-300">
                                {{ $release->category_name ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ number_format($release->size / 1073741824, 2) }} GB
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $release->totalpart ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ userDate($release->adddate, 'Y-m-d H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ userDate($release->postdate, 'Y-m-d H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $release->grabs ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex gap-2">
                                <a href="{{ url('/details/' . $release->guid) }}"
                                   class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="View Details"
                                   target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ url('admin/release-edit?id=' . $release->guid . '&action=view') }}"
                                   class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                       class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                        @click="deleteRelease($event)"
                                       data-delete-release="{{ $release->guid }}"
                                       data-delete-url="{{ url('admin/release-delete/' . $release->guid) }}"
                                       title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>

            <x-admin.pagination :paginator="$releaselist" />
        @else
            <x-admin.empty-state
                icon="fas fa-list"
                title="No releases found"
                message="{{ request('search') || (request()->filled('category_id') && (int) request('category_id') !== -1) ? 'No releases match your filters.' : 'No releases are currently available in the system.' }}"
            />
        @endif
    </x-admin.card>
</div>
@endsection
