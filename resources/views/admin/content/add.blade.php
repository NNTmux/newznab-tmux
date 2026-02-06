@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fa fa-file-alt mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Content Form -->
        <form method="post" action="{{ url('admin/content-add') }}" class="p-6">
            @csrf
            <input type="hidden" name="action" value="submit">
            @if(!empty($content['id']))
                <input type="hidden" name="id" value="{{ is_array($content) ? $content['id'] : $content->id }}">
            @endif

            <div class="space-y-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="title"
                           name="title"
                           value="{{ is_array($content) ? ($content['title'] ?? '') : ($content->title ?? '') }}"
                           required
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- URL -->
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        URL
                    </label>
                    <input type="text"
                           id="url"
                           name="url"
                           value="{{ is_array($content) ? ($content['url'] ?? '') : ($content->url ?? '') }}"
                           placeholder="/page-slug or https://example.com"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400">
                    <p class="mt-1 text-sm text-gray-500">Internal URL (e.g., /about) or external URL (e.g., https://example.com)</p>
                </div>

                <!-- Body -->
                <div>
                    <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Body Content
                    </label>
                    <textarea id="body"
                              name="body"
                              rows="15"
                              data-tinymce-api-key="{{ config('tinymce.api_key', 'no-api-key') }}"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">{{ is_array($content) ? trim(($content['body'] ?? ''), '\'"') : trim(($content->body ?? ''), '\'"') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">Use the rich text editor to format your content</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Content Type -->
                    <div>
                        <label for="contenttype" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Content Type <span class="text-red-500">*</span>
                        </label>
                        <select id="contenttype"
                                name="contenttype"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($contenttypelist as $typeId => $typeName)
                                <option value="{{ $typeId }}"
                                    {{ (is_array($content) ? ($content['contenttype'] ?? '') : ($content->contenttype ?? '')) == $typeId ? 'selected' : '' }}>
                                    {{ $typeName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Visible To <span class="text-red-500">*</span>
                        </label>
                        <select id="role"
                                name="role"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($rolelist as $roleId => $roleName)
                                <option value="{{ $roleId }}"
                                    {{ (is_array($content) ? ($content['role'] ?? '') : ($content->role ?? '')) == $roleId ? 'selected' : '' }}>
                                    {{ $roleName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status"
                                name="status"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            @foreach($status_ids as $index => $statusId)
                                <option value="{{ $statusId }}"
                                    {{ (is_array($content) ? ($content['status'] ?? '') : ($content->status ?? '')) == $statusId ? 'selected' : '' }}>
                                    {{ $status_names[$index] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Ordinal -->
                    <div>
                        <label for="ordinal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Order (Ordinal)
                        </label>
                        <input type="number"
                               id="ordinal"
                               name="ordinal"
                               value="{{ is_array($content) ? ($content['ordinal'] ?? 0) : ($content->ordinal ?? 0) }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                        <p class="mt-1 text-sm text-gray-500">Lower numbers appear first</p>
                    </div>
                </div>

                <!-- Meta Description -->
                <div>
                    <label for="metadescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Meta Description
                    </label>
                    <textarea id="metadescription"
                              name="metadescription"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">{{ is_array($content) ? ($content['metadescription'] ?? '') : ($content->metadescription ?? '') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">SEO meta description</p>
                </div>

                <!-- Meta Keywords -->
                <div>
                    <label for="metakeywords" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Meta Keywords
                    </label>
                    <input type="text"
                           id="metakeywords"
                           name="metakeywords"
                           value="{{ is_array($content) ? ($content['metakeywords'] ?? '') : ($content->metakeywords ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="mt-1 text-sm text-gray-500">Comma-separated keywords for SEO</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-save mr-2"></i>{{ !empty($content['id']) ? 'Update' : 'Create' }} Content
                    </button>
                    <a href="{{ url('admin/content-list') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

