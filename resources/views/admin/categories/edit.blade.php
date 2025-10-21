@extends('layouts.admin')

@section('title', $title ?? 'Category Edit')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-{{ $isCreate ?? false ? 'plus' : 'edit' }} mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('/admin/category-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Categories
                </a>
            </div>
        </div>

        <div class="px-6 py-6">
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-600 text-red-800 dark:text-red-200 rounded">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </div>
            @endif

            <form action="{{ url($isCreate ?? false ? '/admin/category-add?action=submit' : '/admin/category-edit?action=submit') }}" method="POST" id="categoryForm">
                @csrf
                @if(!($isCreate ?? false) && $category)
                    <input type="hidden" name="id" value="{{ $category->id }}"/>
                @endif

                @if($isCreate ?? false)
                    <!-- ID (editable for new categories) -->
                    <div class="mb-6">
                        <label for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Category ID: <span class="text-gray-500 text-xs">(Optional - will auto-generate if left empty)</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa fa-hashtag text-gray-400"></i>
                            </div>
                            <input type="number" id="id" name="id"
                                   class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="Leave empty for auto-generated ID"
                                   value="{{ old('id') }}"/>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Specify a custom numeric ID or leave empty to auto-generate. Cannot be changed after creation.</p>
                    </div>

                    <!-- Title (editable for new categories) -->
                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Title: <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa fa-tag text-gray-400"></i>
                            </div>
                            <input type="text" id="title" name="title" required
                                   class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                                   placeholder="Enter category title"
                                   value="{{ old('title') }}"/>
                        </div>
                    </div>
                @else
                    <!-- ID (read-only for existing categories) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category ID:</label>
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-lg border border-gray-200 dark:border-gray-700 font-mono">
                            {{ $category->id ?? 'N/A' }}
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Category ID cannot be changed after creation</p>
                    </div>

                    <!-- Title (read-only for existing categories) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title:</label>
                        <p class="px-4 py-2 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-lg border border-gray-200 dark:border-gray-700">
                            {{ $category->title ?? 'N/A' }}
                        </p>
                    </div>
                @endif

                <!-- Parent Category (dropdown selector for both create and edit) -->
                <div class="mb-6">
                    <label for="root_categories_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Root Category:
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-folder-open text-gray-400"></i>
                        </div>
                        <select id="root_categories_id" name="root_categories_id"
                                class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200">
                            <option value="">-- No Root Category --</option>
                            @foreach($rootCategories ?? [] as $rootCat)
                                <option value="{{ $rootCat->id }}" {{ ($category->root_categories_id ?? null) == $rootCat->id ? 'selected' : '' }}>
                                    {{ $rootCat->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select a root category from the root_categories table</p>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description:</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-align-left text-gray-400"></i>
                        </div>
                        <input type="text" id="description" name="description"
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                               value="{{ $category->description ?? '' }}"
                               placeholder="Brief explanation of what belongs in this category"/>
                    </div>
                </div>


                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="window.location='{{ url('/admin/category-list') }}'"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition">
                        <i class="fa fa-save mr-2"></i>{{ $isCreate ?? false ? 'Create Category' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Scripts moved to resources/js/csp-safe.js --}}
@endsection

