@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="contentToggle">
    <x-admin.page-header :title="$title" icon="fas fa-file-alt">
        <x-slot:actions>
            <a href="{{ url('admin/content-add?action=add') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Content
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        @if(count($contentlist) > 0)
            <div class="rounded-lg border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-900/60 dark:bg-primary-950/40 dark:text-primary-200">
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
                            <x-admin.badge tone="neutral">{{ count($group['items']) }} items</x-admin.badge>
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
                                                class="content-drag-handle cursor-grab rounded p-1 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:text-gray-300"
                                                data-drag-handle
                                                draggable="true"
                                                title="Drag to reorder {{ Str::lower($group['label']) }}"
                                                aria-label="Drag to reorder {{ Str::lower($group['label']) }}">
                                            <i aria-hidden="true" class="fas fa-grip-vertical"></i>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $item->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ filled($item->title) ? $item->title : 'Untitled' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if(!empty($item->url))
                                            <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300">
                                                {{ Str::limit($item->url, 30) }}
                                            </a>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($item->contenttype == 1)
                                            <x-admin.badge tone="primary">Useful Link</x-admin.badge>
                                        @elseif($item->contenttype == 2)
                                            <x-admin.badge tone="success">Article</x-admin.badge>
                                        @elseif($item->contenttype == 3)
                                            <x-admin.badge tone="info">Homepage</x-admin.badge>
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
                                            <x-admin.badge tone="success" icon="fas fa-check">Enabled</x-admin.badge>
                                        @else
                                            <x-admin.badge tone="danger" icon="fas fa-times">Disabled</x-admin.badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" data-ordinal-cell>{{ $item->ordinal ?? 0 }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-2">
                                            <a aria-label="Edit" href="{{ url('admin/content-add?id=' . $item->id) }}"
                                               class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                               title="Edit">
                                                <i aria-hidden="true" class="fas fa-edit"></i>
                                            </a>
                                            <button aria-label="{{ $item->status == 1 ? 'Disable' : 'Enable' }}" type="button"
                                                    class="content-toggle-status text-{{ $item->status == 1 ? 'green' : 'gray' }}-600 dark:text-{{ $item->status == 1 ? 'green' : 'gray' }}-400 hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-900 dark:hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-300"
                                                    data-content-id="{{ $item->id }}"
                                                    data-current-status="{{ $item->status }}"
                                                    x-on:click.prevent="toggleStatus({{ $item->id }}, {{ $item->status }}, $el)"
                                                    title="{{ $item->status == 1 ? 'Disable' : 'Enable' }}">
                                                <i aria-hidden="true" class="fas fa-toggle-{{ $item->status == 1 ? 'on' : 'off' }}"></i>
                                            </button>
                                            <button aria-label="{{ filled($item->title) ? $item->title : 'Untitled' }}" type="button"
                                                    class="content-delete text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
                                                    data-content-id="{{ $item->id }}"
                                                    data-content-title="{{ filled($item->title) ? $item->title : 'Untitled' }}"
                                                    x-on:click.prevent="deleteContent({{ $item->id }}, @js(filled($item->title) ? $item->title : 'Untitled'), $el)"
                                                    title="Delete">
                                                <i aria-hidden="true" class="fas fa-trash"></i>
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

