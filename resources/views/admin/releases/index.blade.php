@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-list" />

        @if(count($releaselist) > 0)
            <x-admin.data-table>
                <x-slot:head>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $release->id }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="text-gray-900 dark:text-gray-100 font-medium max-w-md wrap-break-word break-all" title="{{ $release->searchname }}">
                                {{ $release->searchname }}
                            </div>
                            <div class="text-gray-500 dark:text-gray-400 text-xs mt-1 max-w-md truncate" title="{{ $release->name }}">
                                {{ $release->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300">
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
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
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
            <x-empty-state
                icon="fas fa-list"
                title="No releases found"
                message="No releases are currently available in the system."
            />
        @endif
    </x-admin.card>
</div>
@endsection

