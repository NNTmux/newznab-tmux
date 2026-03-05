@extends('layouts.admin')

@section('title', $title ?? 'Category List')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-folder-open">
            <x-slot:actions>
                <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition">
                    <i class="fas fa-plus mr-2"></i>Add New Category
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.info-alert>
            Make a category inactive to remove it from the menu. This does not prevent binaries being matched into an
            appropriate category. Disable preview prevents ffmpeg being used for releases in the category.
        </x-admin.info-alert>

        @if(count($categorylist) > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th align="center" width="20">
                        <div class="flex items-center justify-center gap-2">
                            <span>ID</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="?sort=id&order=asc" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ (request('sort') == 'id' && request('order') == 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-numeric-down text-xs"></i>
                                </a>
                                <a href="?sort=id&order=desc" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ (request('sort') == 'id' && request('order') == 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-numeric-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Title</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="?sort=title&order=asc" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ (request('sort') == 'title' && request('order') == 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="?sort=title&order=desc" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ (request('sort') == 'title' && request('order') == 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>Parent</x-admin.th>
                    <x-admin.th align="center" width="24">Status</x-admin.th>
                    <x-admin.th align="center" width="24">Preview</x-admin.th>
                    <x-admin.th align="center" width="28">Actions</x-admin.th>
                </x-slot:head>

                @foreach($categorylist as $category)
                    <tr id="row-{{ $category->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $category->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ url('/admin/category-edit?id=' . $category->id) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
                                {{ $category->title }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            @if($category->parent)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                    {{ $category->parent->title }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($category->status == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fas fa-check-circle mr-1"></i>Active
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                    <i class="fas fa-times-circle mr-1"></i>Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($category->disablepreview == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                    <i class="fas fa-times-circle mr-1"></i>Disabled
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fas fa-check-circle mr-1"></i>Enabled
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ url('/admin/category-edit?id=' . $category->id) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                   title="Edit Category">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="{{ url('/admin/category-delete?id=' . $category->id) }}"
                                   data-confirm-delete
                                   class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                   title="Delete Category">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>
        @else
            <x-empty-state
                icon="fas fa-folder-open"
                title="No categories found"
                message="Start by adding a new category."
                :actionUrl="url('/admin/category-add')"
                actionLabel="Add New Category"
                actionIcon="fas fa-plus"
            />
        @endif

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ count($categorylist) }} categories
                </span>
                <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm transition">
                    <i class="fas fa-plus mr-2"></i>Add New Category
                </a>
            </div>
        </div>
    </x-admin.card>
</div>
@endsection

