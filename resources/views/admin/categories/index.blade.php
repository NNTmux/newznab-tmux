@extends('layouts.admin')

@section('title', $title ?? 'Category List')

@section('content')
<div class="max-w-full px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-folder-open mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition">
                    <i class="fa fa-plus mr-2"></i>Add New Category
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900 border-b border-blue-100 dark:border-blue-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa fa-info-circle text-blue-500 dark:text-blue-400 text-2xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Make a category inactive to remove it from the menu. This does not prevent binaries being matched into an
                        appropriate category. Disable preview prevents ffmpeg being used for releases in the category.
                    </p>
                </div>
            </div>
        </div>

        <!-- Table -->
        @if(count($categorylist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-20">
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
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
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
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Parent</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Preview</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
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
                                            <i class="fa fa-check-circle mr-1"></i>Active
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                            <i class="fa fa-times-circle mr-1"></i>Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($category->disablepreview == 1)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                            <i class="fa fa-times-circle mr-1"></i>Disabled
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            <i class="fa fa-check-circle mr-1"></i>Enabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex gap-2 justify-center">
                                        <a href="{{ url('/admin/category-edit?id=' . $category->id) }}"
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                                           title="Edit Category">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button"
                                                class="category-delete-btn text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                data-category-id="{{ $category->id }}"
                                                title="Delete Category">
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
                <i class="fa fa-exclamation-triangle text-gray-400 dark:text-gray-500 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No categories found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Start by adding a new category.</p>
                <a href="{{ url('/admin/category-add') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-plus mr-2"></i>Add New Category
                </a>
            </div>
        @endif

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Total: {{ count($categorylist) }} categories
                </span>
                <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 text-sm transition">
                    <i class="fa fa-plus mr-2"></i>Add New Category
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div x-data="categoryDeleteModal"
     data-delete-url="{{ url('/admin/category-delete') }}"
     x-show="open"
     x-cloak
     class="fixed inset-0 bg-gray-600/50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Confirm Delete</h3>
                <button type="button" x-on:click="close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="mt-2 px-4 py-3">
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                    Are you sure you want to delete this category? This may impact site functionality and cannot be undone.
                </p>
                <p class="text-sm text-red-600 dark:text-red-400">
                    <strong>Warning:</strong> Deleting a category with child categories or releases will cause orphaned data.
                </p>
            </div>
            <div class="flex gap-3 px-4 py-3">
                <button type="button" x-on:click="close()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <a x-bind:href="deleteUrl()" class="flex-1 px-4 py-2 bg-red-600 dark:bg-red-700 text-white text-center rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition">
                    Delete
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

