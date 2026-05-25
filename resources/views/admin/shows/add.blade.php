@extends('layouts.admin')

@php
    $configJson = json_encode([
        'source' => old('source', 'tvdb'),
        'externalId' => old('external_id', ''),
        'type' => (string) old('type', '0'),
        'lookupUrl' => route('admin.show-add.lookup'),
        'csrfToken' => csrf_token(),
    ], JSON_THROW_ON_ERROR);
@endphp

@section('content')
<div class="space-y-6" x-data="showAddForm" data-config="{{ $configJson }}">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fas fa-tv mr-2"></i>{{ $title }}
            </h1>
            <a href="{{ url('admin/show-list') }}"
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to TV Shows List
            </a>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif
        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <ul class="list-disc list-inside text-red-800 dark:text-red-200">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900 border-b border-blue-100 dark:border-blue-800">
            <div class="flex">
                <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-0.5"></i>
                <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                    <p>Add a TV show to the database by entering any supported external identifier. The matching provider will be queried to fetch title, summary, poster and cross-reference IDs.</p>
                    <ul class="list-disc list-inside text-xs text-blue-600 dark:text-blue-400">
                        <li><strong>TVDB</strong> &mdash; numeric series id (e.g. <code>81189</code>)</li>
                        <li><strong>TVMaze</strong> &mdash; numeric show id (e.g. <code>169</code>)</li>
                        <li><strong>TMDB</strong> &mdash; numeric tv id (e.g. <code>1396</code>)</li>
                        <li><strong>Trakt</strong> &mdash; numeric or slug id (e.g. <code>1388</code> or <code>breaking-bad</code>)</li>
                        <li><strong>IMDB</strong> &mdash; <code>tt0903747</code> or just <code>0903747</code> (tries TMDB → Trakt → TVMaze)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Add Form -->
        <form method="POST" action="{{ route('admin.show-add') }}" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-3">
                    <label for="source" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Source <span class="text-red-500">*</span>
                    </label>
                    <select id="source" name="source" x-model="source" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        @foreach($sources as $s)
                            <option value="{{ $s }}" @selected(old('source', 'tvdb') === $s)>{{ strtoupper($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-5">
                    <label for="external_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        External ID <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="external_id" name="external_id" x-model="externalId" required
                           x-bind:placeholder="placeholder"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                </div>

                <div class="md:col-span-2">
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                    <select id="type" name="type" x-model="type"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">TV</option>
                        <option value="2">Anime</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex gap-2">
                    <button type="button"
                            @click="preview"
                            x-bind:class="buttonClasses"
                            x-bind:disabled="loading">
                        <i class="fas fa-search mr-1"></i><span x-text="buttonLabel"></span>
                    </button>
                </div>
            </div>

            <!-- Preview Block -->
            <div x-show="hasPreview"
                 x-cloak
                 class="mt-6 p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="flex gap-4">
                    <template x-if="hasPoster">
                        <img x-bind:src="posterUrl" alt="" class="w-32 h-auto rounded shadow" />
                    </template>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="title"></h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                            <span x-text="started"></span>
                            <template x-if="hasPublisher">
                                <span>&nbsp;&middot;&nbsp;<span x-text="publisher"></span></span>
                            </template>
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-3" x-text="summary"></p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <template x-for="entry in idEntries" x-bind:key="entry.label">
                                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-100 rounded">
                                    <span x-text="entry.label"></span>: <span x-text="entry.value"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="hasError"
                 x-cloak
                 class="mt-4 p-3 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg text-sm text-red-700 dark:text-red-200"
                 x-text="previewError"></div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add TV Show
                </button>
                <a href="{{ url('admin/show-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

