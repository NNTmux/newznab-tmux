@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800">
                    <i class="fa fa-edit mr-2"></i>{{ $title ?? 'Binary Black/Whitelist Edit' }}
                </h1>
                <a href="{{ url('/admin/binaryblacklist-list') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fa fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ url('/admin/binaryblacklist-edit?action=submit') }}" method="POST" id="blacklistForm" class="px-6 py-6">
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

            <!-- Group Name -->
            <div class="mb-6">
                <label for="groupname" class="block text-sm font-medium text-gray-700 mb-2">
                    Group Name: <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-layer-group text-gray-400"></i>
                    </div>
                    <input type="text"
                           id="groupname"
                           name="groupname"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="{{ htmlspecialchars($regex->groupname ?? '') }}"
                           required>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fa fa-info-circle mr-1"></i>The full name of a valid newsgroup. (Wildcard in the format 'alt.binaries.*')
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
                    <i class="fa fa-info-circle mr-1"></i>The regex to be applied. (Note: Beginning and Ending / are already included)
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
                    <i class="fa fa-info-circle mr-1"></i>A description for this regex
                </p>
            </div>

            <!-- Message Field -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Message Field:
                </label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    @foreach($msgcol_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="msgcol"
                                   id="msgcol{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   {{ ($regex->msgcol ?? 1) == $id ? 'checked' : '' }}>
                            <label for="msgcol{{ $id }}" class="ml-3 text-sm text-gray-700">
                                {{ $msgcol_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fa fa-info-circle mr-1"></i>Which field in the message to apply the black/white list to.
                </p>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Status:
                </label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    @foreach($status_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="status"
                                   id="status{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                            <label for="status{{ $id }}" class="ml-3 text-sm text-gray-700">
                                {{ $status_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fa fa-info-circle mr-1"></i>Only active regexes are applied during the release process.
                </p>
            </div>

            <!-- Type -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Type:
                </label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    @foreach($optype_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="optype"
                                   id="optype{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                   {{ ($regex->optype ?? 1) == $id ? 'checked' : '' }}>
                            <label for="optype{{ $id }}" class="ml-3 text-sm text-gray-700">
                                {{ $optype_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fa fa-info-circle mr-1"></i>Black will exclude all messages for a group which match this regex. White will include only those which match.
                </p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between">
                <a href="{{ url('/admin/binaryblacklist-list') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fa fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" form="blacklistForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fa fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('blacklistForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const groupname = document.getElementById('groupname');
            const regex = document.getElementById('regex');

            if (!groupname.value.trim() || !regex.value.trim()) {
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

