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
    <x-admin.page-header :title="$title" icon="fas fa-tv">
        <x-slot:actions>
            <x-admin.button :href="url('admin/show-list')" tone="gray" icon="fas fa-arrow-left">Back to TV Shows List</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>
    <x-admin.card>

        <!-- Flash Messages -->
        @if($errors->any())
            <div class="mx-6 mt-4 p-4 bg-danger-50 dark:bg-danger-900 border border-danger-200 dark:border-danger-700 rounded-lg">
                <ul class="list-disc list-inside text-danger-800 dark:text-danger-200">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-info-50 dark:bg-info-900 border-b border-info-100 dark:border-info-800">
            <div class="flex">
                <i aria-hidden="true" class="fas fa-info-circle text-info-500 text-xl mr-3 mt-0.5"></i>
                <div class="text-sm text-info-700 dark:text-info-300 space-y-1">
                    <p>Add a TV show to the database by entering any supported external identifier. The matching provider will be queried to fetch title, summary, poster and cross-reference IDs.</p>
                    <ul class="list-disc list-inside text-xs text-info-600 dark:text-info-400">
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
                        Source <span class="text-danger-500">*</span>
                    </label>
                    <select id="source" name="source" x-model="source" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        @foreach($sources as $s)
                            <option value="{{ $s }}" @selected(old('source', 'tvdb') === $s)>{{ strtoupper($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-5">
                    <label for="external_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        External ID <span class="text-danger-500">*</span>
                    </label>
                    <input type="text" id="external_id" name="external_id" x-model="externalId" required
                           x-bind:placeholder="placeholder"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>

                <div class="md:col-span-2">
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                    <select id="type" name="type" x-model="type"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">TV</option>
                        <option value="2">Anime</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex gap-2">
                    <button type="button"
                            @click="preview"
                            x-bind:class="buttonClasses"
                            x-bind:disabled="loading">
                        <i aria-hidden="true" class="fas fa-search mr-1"></i><span x-text="buttonLabel"></span>
                    </button>
                </div>
            </div>

            <!-- Preview Block -->
            <div x-show="hasPreview"
                 x-cloak
                 class="mt-6 p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                <div class="flex gap-4">
                    <template x-if="hasPoster">
                        <img x-bind:src="posterUrl" x-bind:alt="title ? title + ' poster' : 'Show poster'" class="w-32 h-auto rounded shadow" />
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
                                <span class="px-2 py-0.5 bg-primary-100 dark:bg-primary-800 text-primary-800 dark:text-primary-100 rounded">
                                    <span x-text="entry.label"></span>: <span x-text="entry.value"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="hasError"
                 x-cloak
                 class="mt-4 p-3 bg-danger-50 dark:bg-danger-900 border border-danger-200 dark:border-danger-700 rounded-lg text-sm text-danger-700 dark:text-danger-200"
                 x-text="previewError"></div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                    <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add TV Show
                </button>
                <a href="{{ url('admin/show-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
                </a>
            </div>
        </form>
    </x-admin.card>
</div>
@endsection
