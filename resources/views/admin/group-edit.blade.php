@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-edit mr-2"></i>{{ $title ?? 'Group Edit' }}
                </h1>
                <a href="{{ url('/admin/group-list') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Groups
                </a>
            </div>
        </div>

        <!-- Error Message -->
        @if(isset($error) && $error != '')
            <div class="mx-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <i class="fa fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-800">{{ $error }}</p>
                </div>
            </div>
        @endif

        <!-- Form -->
        <form action="{{ url('/admin/group-edit?action=submit') }}" method="POST" id="groupForm" class="px-6 py-6">
            @csrf
            <input type="hidden" name="id" value="{{ $group['id'] ?? '' }}"/>

            <!-- Group Name -->
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Group Name:
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-users text-gray-400"></i>
                    </div>
                    <input type="text"
                           id="name"
                           name="name"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['name'] ?? '' }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Changing the name to an invalid group will break things.
                </p>
            </div>

            <!-- Description -->
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description:
                </label>
                <div class="relative">
                    <div class="absolute top-3 left-3 pointer-events-none">
                        <i class="fa fa-align-left text-gray-400"></i>
                    </div>
                    <textarea id="description"
                              name="description"
                              class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              rows="3">{{ $group['description'] ?? '' }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Brief explanation of this group's content
                </p>
            </div>

            <!-- Backfill Days -->
            <div class="mb-6">
                <label for="backfill_target" class="block text-sm font-medium text-gray-700 mb-2">
                    Backfill Days:
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-calendar text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="backfill_target"
                           name="backfill_target"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['backfill_target'] ?? 0 }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Number of days to attempt to backfill this group. Adjust as necessary.
                </p>
            </div>

            <!-- Minimum Files -->
            <div class="mb-6">
                <label for="minfilestoformrelease" class="block text-sm font-medium text-gray-700 mb-2">
                    Minimum Files To Form Release:
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-file text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="minfilestoformrelease"
                           name="minfilestoformrelease"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['minfilestoformrelease'] ?? 0 }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    The minimum number of files to make a release. If left blank, will use the site wide setting.
                </p>
            </div>

            <!-- Minimum Size -->
            <div class="mb-6">
                <label for="minsizetoformrelease" class="block text-sm font-medium text-gray-700 mb-2">
                    Minimum File Size (bytes):
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-download text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="minsizetoformrelease"
                           name="minsizetoformrelease"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['minsizetoformrelease'] ?? 0 }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    The minimum total size in bytes to make a release. If left blank, will use the site wide setting.
                </p>
            </div>

            <!-- First Record -->
            <div class="mb-6">
                <label for="first_record" class="block text-sm font-medium text-gray-700 mb-2">
                    First Record ID:
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-angle-double-left text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="first_record"
                           name="first_record"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['first_record'] ?? 0 }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    The oldest record number for the group.
                </p>
            </div>

            <!-- Last Record -->
            <div class="mb-6">
                <label for="last_record" class="block text-sm font-medium text-gray-700 mb-2">
                    Last Record ID:
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-angle-double-right text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="last_record"
                           name="last_record"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ $group['last_record'] ?? 0 }}"/>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    The newest record number for the group.
                </p>
            </div>

            <!-- Active Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Active:</label>
                <div class="flex items-center gap-6">
                    <div class="flex items-center">
                        <input type="radio"
                               id="active_1"
                               name="active"
                               value="1"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                               {{ (isset($group['active']) && $group['active'] == 1) ? 'checked' : '' }}>
                        <label for="active_1" class="ml-2 text-sm text-gray-700">Yes</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio"
                               id="active_0"
                               name="active"
                               value="0"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                               {{ (isset($group['active']) && $group['active'] == 0) ? 'checked' : '' }}>
                        <label for="active_0" class="ml-2 text-sm text-gray-700">No</label>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Inactive groups will not have headers downloaded for them.
                </p>
            </div>

            <!-- Backfill Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Backfill:</label>
                <div class="flex items-center gap-6">
                    <div class="flex items-center">
                        <input type="radio"
                               id="backfill_1"
                               name="backfill"
                               value="1"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                               {{ (isset($group['backfill']) && $group['backfill'] == 1) ? 'checked' : '' }}>
                        <label for="backfill_1" class="ml-2 text-sm text-gray-700">Yes</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio"
                               id="backfill_0"
                               name="backfill"
                               value="0"
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                               {{ (isset($group['backfill']) && $group['backfill'] == 0) ? 'checked' : '' }}>
                        <label for="backfill_0" class="ml-2 text-sm text-gray-700">No</label>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    If set to No, backfill will ignore this group. This works even if the above setting is No.
                </p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between">
                <button type="button"
                        onclick="window.location='{{ url('/admin/group-list') }}'"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fa fa-times mr-2"></i>Cancel
                </button>
                <button type="submit"
                        form="groupForm"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fa fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('groupForm');
    form.addEventListener('submit', function(event) {
        const firstRecord = document.getElementById('first_record').value;
        const lastRecord = document.getElementById('last_record').value;

        // Validate record IDs relationship
        if (parseInt(firstRecord) > parseInt(lastRecord) && parseInt(lastRecord) > 0) {
            event.preventDefault();
            alert('First record ID cannot be greater than last record ID');
            return false;
        }
    });
});
</script>
@endpush
@endsection

