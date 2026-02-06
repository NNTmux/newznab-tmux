@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-file-alt mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('admin/content-add?action=add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add New Content
                </a>
            </div>
        </div>

        <!-- Content Table -->
        @if(count($contentlist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">URL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ordinal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($contentlist as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">{{ $item->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ $item->title }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if(!empty($item->url))
                                        <a href="{{ $item->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            {{ Str::limit($item->url, 30) }}
                                        </a>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($item->contenttype == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-100">
                                            Useful Link
                                        </span>
                                    @elseif($item->contenttype == 2)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100">
                                            Article
                                        </span>
                                    @elseif($item->contenttype == 3)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-100">
                                            Homepage
                                        </span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">N/A</span>
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
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100">
                                            <i class="fa fa-check mr-1"></i>Enabled
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100">
                                            <i class="fa fa-times mr-1"></i>Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->ordinal ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a href="{{ url('admin/content-add?id=' . $item->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="content-toggle-status text-{{ $item->status == 1 ? 'green' : 'gray' }}-600 dark:text-{{ $item->status == 1 ? 'green' : 'gray' }}-400 hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-900 dark:hover:text-{{ $item->status == 1 ? 'green' : 'gray' }}-300"
                                                data-content-id="{{ $item->id }}"
                                                data-current-status="{{ $item->status }}"
                                                title="{{ $item->status == 1 ? 'Disable' : 'Enable' }}">
                                            <i class="fa fa-toggle-{{ $item->status == 1 ? 'on' : 'off' }}"></i>
                                        </button>
                                        <button type="button"
                                                class="content-delete text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                data-content-id="{{ $item->id }}"
                                                data-content-title="{{ $item->title }}"
                                                title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-file-alt text-gray-400 dark:text-gray-500 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No content found</h3>
                <p class="text-gray-500 dark:text-gray-400">Create your first content to get started.</p>
            </div>
        @endif
    </div>
</div>
@endsection

