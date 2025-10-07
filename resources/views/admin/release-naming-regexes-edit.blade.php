@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-edit mr-2"></i>{{ $title ?? 'Release Naming Regex Edit' }}
                </h1>
                <a href="{{ url('/admin/release_naming_regexes-list') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fa fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ url('/admin/release_naming_regexes-edit?action=submit') }}" method="POST" id="regexForm" class="px-6 py-6">
            @csrf
            <input type="hidden" name="id" value="{{ $regex->id ?? '' }}"/>

            <!-- Error Message -->
            @if($error)
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex">
                        <i class="fa fa-exclamation-triangle text-red-500 mr-3"></i>
                        <p class="text-red-800">{{ $error }}</p>
                    </div>
                </div>
            @endif

            <!-- Group -->
            <div class="mb-6">
                <label for="group_regex" class="block text-sm font-medium text-gray-700 mb-2">
                    Group: <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-users text-gray-400"></i>
                    </div>
                    <input type="text"
                           id="group_regex"
                           name="group_regex"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ htmlspecialchars($regex->group_regex ?? '') }}"
                           required>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Regex to match against a group or multiple groups. Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br>
                    Example of matching a single group: <code class="bg-gray-100 px-2 py-1 rounded">alt\.binaries\.example</code><br>
                    Example of matching multiple groups: <code class="bg-gray-100 px-2 py-1 rounded">alt\.binaries.*</code>
                </p>
            </div>

            <!-- Regex -->
            <div class="mb-6">
                <label for="regex" class="block text-sm font-medium text-gray-700 mb-2">
                    Regex: <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute top-3 left-3 pointer-events-none">
                        <i class="fa fa-code text-gray-400"></i>
                    </div>
                    <textarea id="regex"
                              name="regex"
                              class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                              rows="4"
                              required>{{ htmlspecialchars($regex->regex ?? '') }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Regex to use when renaming releases.<br>
                    The regex delimiters are not added, you MUST add them. See <a href="http://php.net/manual/en/regexp.reference.delimiters" target="_blank" class="text-blue-600 hover:text-blue-800">this</a> page.<br>
                    To make the regex case insensitive, add <code class="bg-gray-100 px-2 py-1 rounded">i</code> after the last delimiter.
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
                              rows="3">{{ htmlspecialchars($regex->description ?? '') }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Description for this regex. You can include an example release name this regex would match on.
                </p>
            </div>

            <!-- Ordinal -->
            <div class="mb-6">
                <label for="ordinal" class="block text-sm font-medium text-gray-700 mb-2">
                    Ordinal: <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-sort-numeric-asc text-gray-400"></i>
                    </div>
                    <input type="number"
                           id="ordinal"
                           name="ordinal"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Active:
                </label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    @foreach($status_ids as $k => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="status"
                                   id="status{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                            <label for="status{{ $id }}" class="ml-3 text-sm text-gray-700">
                                {{ $status_names[$k] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Only active regex are used during the release naming process.
                </p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between">
                <a href="{{ url('/admin/release_naming_regexes-list') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fa fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" form="regexForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fa fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('regexForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const groupRegex = document.getElementById('group_regex');
            const regex = document.getElementById('regex');
            const ordinal = document.getElementById('ordinal');

            if (!groupRegex.value.trim() || !regex.value.trim() || !ordinal.value) {
                event.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });
    }
});
</script>
@endpush
@endsection

