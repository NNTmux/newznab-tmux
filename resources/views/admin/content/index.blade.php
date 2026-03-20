@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="contentToggle">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-file-alt">
            <x-slot:actions>
                <a href="{{ url('admin/content-add?action=add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fas fa-plus mr-2"></i>Add New Content
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        @if(count($contentlist) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th>ID</x-admin.th>
                    <x-admin.th>Title</x-admin.th>
                    <x-admin.th>URL</x-admin.th>
                    <x-admin.th>Type</x-admin.th>
                    <x-admin.th>Role</x-admin.th>
                    <x-admin.th>Status</x-admin.th>
                    <x-admin.th>Ordinal</x-admin.th>
                    <x-admin.th>Actions</x-admin.th>
                </x-slot:head>

                @foreach($contentlist as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $item->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ filled($item->title) ? $item->title : 'Untitled' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            @if(!empty($item->url))
                                <a href="{{ $item->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    {{ Str::limit($item->url, 30) }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            @if($item->contenttype == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                    Useful Link
                                </span>
                            @elseif($item->contenttype == 2)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    Article
                                </span>
                            @elseif($item->contenttype == 3)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                    Homepage
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            @if($item->role == 1)
                                Everyone
                            @elseif($item->role == 2)
                                Logged in Users
                            @elseif($item->role == 3)
                                Admins
                            @else
                                N/A
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($item->status == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fas fa-check mr-1"></i>Enabled
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                    <i class="fas fa-times mr-1"></i>Disabled
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->ordinal ?? 0 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex gap-2">
                                <a href="{{ url('admin/content-add?id=' . $item->id) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                        class="content-toggle-status text-{{ $item->status == 1 ? 'green' : 'gray' }}-600 dark:text-{{ $item->status == 1 ? 'green' : 'gray' }}-400 hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-900 dark:hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-300"
                                        data-content-id="{{ $item->id }}"
                                        data-current-status="{{ $item->status }}"
                                        x-on:click.prevent="toggleStatus({{ $item->id }}, {{ $item->status }}, $el)"
                                        title="{{ $item->status == 1 ? 'Disable' : 'Enable' }}">
                                    <i class="fas fa-toggle-{{ $item->status == 1 ? 'on' : 'off' }}"></i>
                                </button>
                                <button type="button"
                                        class="content-delete text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                        data-content-id="{{ $item->id }}"
                                        data-content-title="{{ filled($item->title) ? $item->title : 'Untitled' }}"
                                        x-on:click.prevent="deleteContent({{ $item->id }}, @js(filled($item->title) ? $item->title : 'Untitled'), $el)"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
        @else
            <x-empty-state
                icon="fas fa-file-alt"
                title="No content found"
                message="Create your first content to get started."
                :actionUrl="url('admin/content-add?action=add')"
                actionLabel="Add New Content"
                actionIcon="fas fa-plus"
            />
        @endif
    </x-admin.card>
</div>
@endsection

