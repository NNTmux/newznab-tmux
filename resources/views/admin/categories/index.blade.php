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
                    <x-admin.th align="center" width="20" :sort="$sort === 'id' ? $order : null" :href="request()->fullUrlWithQuery(['sort' => 'id', 'order' => $sort === 'id' && $order === 'asc' ? 'desc' : 'asc', 'page' => null])">ID</x-admin.th>
                    <x-admin.th :sort="$sort === 'title' ? $order : null" :href="request()->fullUrlWithQuery(['sort' => 'title', 'order' => $sort === 'title' && $order === 'asc' ? 'desc' : 'asc', 'page' => null])">Title</x-admin.th>
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
                                <x-admin.badge tone="neutral">{{ $category->parent->title }}</x-admin.badge>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($category->status == 1)
                                <x-admin.badge tone="success" icon="fas fa-check-circle">Active</x-admin.badge>
                            @else
                                <x-admin.badge tone="danger" icon="fas fa-times-circle">Inactive</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($category->disablepreview == 1)
                                <x-admin.badge tone="warning" icon="fas fa-times-circle">Disabled</x-admin.badge>
                            @else
                                <x-admin.badge tone="success" icon="fas fa-check-circle">Enabled</x-admin.badge>
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
                                        class="text-danger-600 dark:text-danger-400 hover:text-danger-900 dark:hover:text-danger-300"
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
