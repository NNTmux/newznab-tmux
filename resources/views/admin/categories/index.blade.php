@extends('layouts.admin')

@section('title', $title ?? 'Category List')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-folder-open">
        <x-slot:actions>
            <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 transition">
                <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Category
            </a>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

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
                                <a aria-label="Sort Ascending" href="?sort=id&order=asc" class="text-gray-400 hover:text-link dark:hover:text-primary-400 {{ (request('sort') == 'id' && request('order') == 'asc') ? 'text-link dark:text-primary-400' : '' }}" title="Sort Ascending">
                                    <i aria-hidden="true" class="fas fa-sort-numeric-down text-xs"></i>
                                </a>
                                <a aria-label="Sort Descending" href="?sort=id&order=desc" class="text-gray-400 hover:text-link dark:hover:text-primary-400 {{ (request('sort') == 'id' && request('order') == 'desc') ? 'text-link dark:text-primary-400' : '' }}" title="Sort Descending">
                                    <i aria-hidden="true" class="fas fa-sort-numeric-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Title</span>
                            <div class="flex flex-col gap-0.5">
                                <a aria-label="Sort Ascending" href="?sort=title&order=asc" class="text-gray-400 hover:text-link dark:hover:text-primary-400 {{ (request('sort') == 'title' && request('order') == 'asc') ? 'text-link dark:text-primary-400' : '' }}" title="Sort Ascending">
                                    <i aria-hidden="true" class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a aria-label="Sort Descending" href="?sort=title&order=desc" class="text-gray-400 hover:text-link dark:hover:text-primary-400 {{ (request('sort') == 'title' && request('order') == 'desc') ? 'text-link dark:text-primary-400' : '' }}" title="Sort Descending">
                                    <i aria-hidden="true" class="fas fa-sort-alpha-down-alt text-xs"></i>
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
                            <a href="{{ url('/admin/category-edit?id=' . $category->id) }}" class="font-medium text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">
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
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200">
                                    <i aria-hidden="true" class="fas fa-check-circle mr-1"></i>Active
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                    <i aria-hidden="true" class="fas fa-times-circle mr-1"></i>Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($category->disablepreview == 1)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200">
                                    <i aria-hidden="true" class="fas fa-times-circle mr-1"></i>Disabled
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200">
                                    <i aria-hidden="true" class="fas fa-check-circle mr-1"></i>Enabled
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <div class="flex gap-2 justify-center">
                                <a aria-label="Edit Category" href="{{ url('/admin/category-edit?id=' . $category->id) }}"
                                   class="text-link dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                                   title="Edit Category">
                                    <i aria-hidden="true" class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.category-delete') }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="id" value="{{ $category->id }}">
                                    <button aria-label="Delete Category" type="submit"
                                        data-confirm-delete
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                        title="Delete Category">
                                        <i aria-hidden="true" class="fas fa-trash"></i>
                                    </button>
                                </form>
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
                <a href="{{ url('/admin/category-add') }}" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 text-sm transition">
                    <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add New Category
                </a>
            </div>
        </div>
    </x-admin.card>
</div>
@endsection
