@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm max-w-3xl mx-auto">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-plus mr-2"></i>Create New Promotion
            </h1>
        </div>

        <!-- Form -->
        <form action="{{ route('admin.promotions.store') }}" method="POST" class="p-6">
            @csrf

            <!-- Name -->
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Promotion Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                       required>
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description
                </label>
                <textarea name="description" id="description" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Applicable Roles -->
            <div class="mb-6">
                <label for="applicable_roles" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Applicable Custom Roles
                </label>
                <select name="applicable_roles[]" id="applicable_roles" multiple
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                        size="6">
                    @foreach($customRoles as $role)
                        <option value="{{ $role->id }}" {{ is_array(old('applicable_roles')) && in_array($role->id, old('applicable_roles')) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Hold Ctrl (Cmd on Mac) to select multiple roles. Leave empty to apply to all custom roles (excludes Admin, User, Moderator, Disabled, Friend)
                </p>
                @error('applicable_roles')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Additional Days -->
            <div class="mb-6">
                <label for="additional_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Additional Days <span class="text-red-500">*</span>
                </label>
                <input type="number" name="additional_days" id="additional_days" value="{{ old('additional_days', 0) }}"
                       min="0"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                       required>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Number of days to add to role expiry date when upgraded</p>
                @error('additional_days')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Start Date
                    </label>
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank for no start restriction</p>
                    @error('start_date')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        End Date
                    </label>
                    <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank for no end restriction</p>
                    @error('end_date')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Is Active -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', true) ? 'checked' : '' }}
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uncheck to create as inactive</p>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('admin.promotions.index') }}"
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-save mr-2"></i>Create Promotion
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

