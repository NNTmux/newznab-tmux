@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Category Regex Edit'" icon="fas fa-edit">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/category_regexes-list')" tone="gray" icon="fas fa-arrow-left">Back to List</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Form -->
        <form action="{{ url('/admin/category_regexes-edit?action=submit') }}" method="POST" id="regexForm" class="px-6 py-6">
            @csrf
            <x-form-errors class="mb-6" />
            <input type="hidden" name="id" value="{{ $regex->id ?? '' }}"/>

            <!-- Error Message -->
            @if($error)
                <div class="mb-6 p-4 bg-danger-50 dark:bg-danger-950/40 border border-danger-200 rounded-lg">
                    <div class="flex">
                        <i aria-hidden="true" class="fas fa-exclamation-triangle text-danger-500 mr-3"></i>
                        <p class="text-danger-800 dark:text-danger-200">{{ $error }}</p>
                    </div>
                </div>
            @endif

            <!-- Group -->
            <div class="mb-6">
                <label for="group_regex" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Group: <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i aria-hidden="true" class="fas fa-users text-gray-400"></i>
                    </div>
                    <input type="text"
                           id="group_regex"
                           name="group_regex"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           value="{{ $regex->group_regex ?? '' }}"
                           required>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Regex to match against a group or multiple groups. Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br>
                    Example of matching a single group: <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">alt\.binaries\.example</code><br>
                    Example of matching multiple groups: <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">alt\.binaries.*</code>
                </p>
            </div>

            <!-- Regex -->
            <div class="mb-6">
                <label for="regex" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Regex: <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute top-3 left-3 pointer-events-none">
                        <i aria-hidden="true" class="fas fa-code text-gray-400"></i>
                    </div>
                    <textarea id="regex"
                              name="regex"
                              class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                              rows="4"
                              required>{{ regex_display_value($regex->regex ?? '') }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Regex to use when categorizing releases.<br>
                    The regex delimiters are not added, you MUST add them. See <a href="https://www.php.net/manual/en/regexp.reference.delimiters" target="_blank" rel="noopener noreferrer" class="text-link dark:text-primary-400 hover:text-primary-800">this</a> page.<br>
                    To make the regex case insensitive, add <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">i</code> after the last delimiter.
                </p>
            </div>

            <!-- Description -->
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description:
                </label>
                <div class="relative">
                    <div class="absolute top-3 left-3 pointer-events-none">
                        <i aria-hidden="true" class="fas fa-align-left text-gray-400"></i>
                    </div>
                    <textarea id="description"
                              name="description"
                              class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              rows="3">{{ $regex->description ?? '' }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Description for this regex. You can include an example usenet subject this regex would match on.
                </p>
            </div>

            <!-- Ordinal -->
            <div class="mb-6">
                <label for="ordinal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Ordinal: <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i aria-hidden="true" class="fas fa-sort-numeric-asc text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="ordinal"
                           name="ordinal"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           value="{{ $regex->ordinal ?? 0 }}"
                           min="0"
                           required>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    The order to run this regex in. Must be a number, 0 or higher.<br>
                    If multiple regex have the same ordinal, MySQL will randomly sort them.
                </p>
            </div>

            <!-- Active Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Active:
                </label>
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    @foreach($status_ids as $k => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="status"
                                   id="status{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                   {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                            <label for="status{{ $id }}" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $status_names[$k] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Only active regex are used during the collection matching process.
                </p>
            </div>

            <!-- Category -->
            <div class="mb-6">
                <label for="categories_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Category: <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i aria-hidden="true" class="fas fa-folder-open text-gray-400"></i>
                    </div>
                    <select id="categories_id"
                            name="categories_id"
                            class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            required>
                        @foreach($category_ids as $k => $catId)
                            <option value="{{ $catId }}" {{ ($regex->categories_id ?? '') == $catId ? 'selected' : '' }}>
                                {{ $category_names[$k] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Select a category which releases matched to this regex will go into.
                </p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50">
            <div class="flex justify-between">
                <a href="{{ url('/admin/category_regexes-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                    <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" form="regexForm" class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white rounded-lg hover:bg-success-700">
                    <i aria-hidden="true" class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </div>
    </x-admin.card>
</div>

@endsection

