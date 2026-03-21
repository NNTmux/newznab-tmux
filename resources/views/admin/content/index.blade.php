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
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-200">
                Drag rows to update the order within each content group. Reordering Homepage items will not affect Useful Links.
            </div>

            <div class="space-y-6">
                @foreach($contentGroups as $group)
                    <section class="space-y-3" data-content-group data-content-type="{{ $group['contenttype'] }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $group['label'] }}</h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Drag to reorder only items in this group.</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ count($group['items']) }} items
                            </span>
                        </div>

                        <x-admin.data-table class="select-none">
                            <x-slot:head>
                                <x-admin.th class="w-12">
                                    <span class="sr-only">Drag</span>
                                </x-admin.th>
                                <x-admin.th>ID</x-admin.th>
                                <x-admin.th>Title</x-admin.th>
                                <x-admin.th>URL</x-admin.th>
                                <x-admin.th>Type</x-admin.th>
                                <x-admin.th>Role</x-admin.th>
                                <x-admin.th>Status</x-admin.th>
                                <x-admin.th>Ordinal</x-admin.th>
                                <x-admin.th>Actions</x-admin.th>
                            </x-slot:head>

                            @foreach($group['items'] as $item)
                                <tr class="content-row hover:bg-gray-50 dark:hover:bg-gray-700"
                                    data-content-id="{{ $item->id }}"
                                    data-content-type="{{ $item->contenttype }}"
                                    data-ordinal="{{ $item->ordinal ?? 0 }}">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-400 dark:text-gray-500">
                                        <button type="button"
                                                class="content-drag-handle cursor-grab rounded p-1 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                                                data-drag-handle
                                                draggable="true"
                                                title="Drag to reorder {{ Str::lower($group['label']) }}"
                                                aria-label="Drag to reorder {{ Str::lower($group['label']) }}">
                                            <i class="fas fa-grip-vertical"></i>
                                        </button>
                                    </td>
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
                                    <td class="px-6 py-4 whitespace-nowrap" data-status-cell>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" data-ordinal-cell>{{ $item->ordinal ?? 0 }}</td>
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
                    </section>
                @endforeach
            </div>
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

