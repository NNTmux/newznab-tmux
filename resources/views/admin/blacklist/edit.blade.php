@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title ?? 'Binary Black/Whitelist Edit'" icon="fas fa-edit">
        <x-slot:actions>
            <x-admin.button :href="url('/admin/binaryblacklist-list')" tone="gray" icon="fas fa-arrow-left">Back to List</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Form -->
        <form action="{{ url('/admin/binaryblacklist-edit?action=submit') }}" method="POST" id="blacklistForm" class="px-6 py-6">
            @csrf
            <x-form-errors class="mb-6" />
            <input type="hidden" name="id" value="{{ $regex->id ?? '' }}"/>

            <!-- Error Message -->
            @if($error)
                <div class="mb-6 p-4 bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg">
                    <div class="flex">
                        <i aria-hidden="true" class="fas fa-exclamation-triangle text-danger-500 dark:text-danger-400 mr-3"></i>
                        <p class="text-danger-800 dark:text-danger-300">{{ $error }}</p>
                    </div>
                </div>
            @endif

            <!-- Group Name -->
            <div class="mb-6">
                <label for="groupname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Group Name: <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i aria-hidden="true" class="fas fa-layer-group text-gray-400"></i>
                    </div>
                    <input type="text"
                           id="groupname"
                           name="groupname"
                           class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           value="{{ htmlspecialchars($regex->groupname ?? '') }}"
                           required>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>The full name of a valid newsgroup. (Wildcard in the format 'alt.binaries.*')
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
                              class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                              rows="4"
                              required>{{ htmlspecialchars($regex->regex ?? '') }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>The regex to be applied. (Note: Beginning and Ending / are already included)
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
                              class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              rows="3">{{ htmlspecialchars($regex->description ?? '') }}</textarea>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>A description for this regex
                </p>
            </div>

            <!-- Message Field -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Message Field:
                </label>
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    @foreach($msgcol_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="msgcol"
                                   id="msgcol{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                   {{ ($regex->msgcol ?? 1) == $id ? 'checked' : '' }}>
                            <label for="msgcol{{ $id }}" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $msgcol_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>Which field in the message to apply the black/white list to.
                </p>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Status:
                </label>
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    @foreach($status_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="status"
                                   id="status{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                   {{ ($regex->status ?? 1) == $id ? 'checked' : '' }}>
                            <label for="status{{ $id }}" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $status_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>Only active regexes are applied during the release process.
                </p>
            </div>

            <!-- Type -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Type:
                </label>
                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    @foreach($optype_ids as $i => $id)
                        <div class="flex items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="radio"
                                   name="optype"
                                   id="optype{{ $id }}"
                                   value="{{ $id }}"
                                   class="w-4 h-4 text-link dark:text-primary-400 border-gray-300 dark:border-gray-600 focus:ring-primary-500"
                                   {{ ($regex->optype ?? 1) == $id ? 'checked' : '' }}>
                            <label for="optype{{ $id }}" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $optype_names[$i] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>Black will exclude all messages for a group which match this regex. White will include only those which match.
                </p>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
            <div class="flex justify-between">
                <a href="{{ url('/admin/binaryblacklist-list') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500">
                    <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" form="blacklistForm" class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white rounded-lg hover:bg-success-700 dark:hover:bg-success-800">
                    <i aria-hidden="true" class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </div>
    </x-admin.card>
</div>

@endsection
