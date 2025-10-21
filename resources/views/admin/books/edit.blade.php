@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-edit mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ route('admin.book-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="p-6">
            <form method="POST" action="{{ url('admin/book-edit?id=' . $book['id']) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="id" value="{{ $book['id'] }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="title"
                                   name="title"
                                   value="{{ $book['title'] ?? '' }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Author -->
                        <div>
                            <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Author
                            </label>
                            <input type="text"
                                   id="author"
                                   name="author"
                                   value="{{ $book['author'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Publisher -->
                        <div>
                            <label for="publisher" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Publisher
                            </label>
                            <input type="text"
                                   id="publisher"
                                   name="publisher"
                                   value="{{ $book['publisher'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Publish Date -->
                        <div>
                            <label for="publishdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Publish Date
                            </label>
                            <input type="date"
                                   id="publishdate"
                                   name="publishdate"
                                   value="{{ isset($book['publishdate']) && $book['publishdate'] ? date('Y-m-d', $book['publishdate']) : '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- ASIN -->
                        <div>
                            <label for="asin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                ASIN
                            </label>
                            <input type="text"
                                   id="asin"
                                   name="asin"
                                   value="{{ $book['asin'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- URL -->
                        <div>
                            <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Amazon URL
                            </label>
                            <input type="url"
                                   id="url"
                                   name="url"
                                   value="{{ $book['url'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            @if(!empty($book['url']))
                                <a href="{{ $book['url'] }}" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">
                                    <i class="fas fa-external-link-alt mr-1"></i>View on Amazon
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Current Cover -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Current Cover
                            </label>
                            <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                                @if(isset($book['cover']) && $book['cover'] == 1)
                                    <img src="{{ asset('storage/covers/book/' . $book['id'] . '.jpg') }}"
                                         alt="{{ $book['title'] }}"
                                         class="max-w-full h-auto mx-auto rounded shadow-lg img-max-h-400"
                                         data-fallback-src="{{ asset('images/no-cover.png') }}">
                                @else
                                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                                        <i class="fas fa-book text-6xl mb-3"></i>
                                        <p>No cover image available</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Upload New Cover -->
                        <div>
                            <label for="cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Upload New Cover
                            </label>
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                                <input type="file"
                                       id="cover"
                                       name="cover"
                                       accept="image/*"
                                       class="w-full text-sm text-gray-500 dark:text-gray-400
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100
                                              dark:file:bg-blue-900 dark:file:text-blue-200">
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    PNG, JPG, GIF up to 10MB
                                </p>
                            </div>
                        </div>

                        <!-- Book Info -->
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Book Information</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">ID:</dt>
                                    <dd class="text-gray-900 dark:text-gray-200 font-mono">{{ $book['id'] }}</dd>
                                </div>
                                @if(isset($book['created_at']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Created:</dt>
                                    <dd class="text-gray-900 dark:text-gray-200">{{ $book['created_at'] }}</dd>
                                </div>
                                @endif
                                @if(isset($book['updated_at']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Updated:</dt>
                                    <dd class="text-gray-900 dark:text-gray-200">{{ $book['updated_at'] }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                    <a href="{{ route('admin.book-list') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Scripts moved to resources/js/csp-safe.js --}}
@endsection

