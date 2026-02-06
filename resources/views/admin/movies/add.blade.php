@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-film mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Success/Error/Warning Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fa fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif

        <!-- Info Alert -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-blue-900 border-b border-blue-100">
            <div class="flex">
                <i class="fa fa-info-circle text-blue-500 text-xl mr-3"></i>
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="mb-2">
                        Enter an IMDB ID (numeric only, without the 'tt' prefix) to add movie information to the database.
                    </p>
                    <p class="text-xs text-blue-600 dark:text-blue-400">
                        Example: For <code class="px-1 bg-blue-100 dark:bg-blue-800 rounded">https://www.imdb.com/title/tt0111161/</code>, enter <code class="px-1 bg-blue-100 dark:bg-blue-800 rounded">0111161</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Movie Form -->
        <form method="GET" action="{{ url('admin/movie-add') }}" class="p-6">
            <div class="max-w-2xl">
                <div class="mb-6">
                    <label for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        IMDB ID <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 dark:text-gray-400 font-mono">tt</span>
                            </div>
                            <input type="text"
                                   id="id"
                                   name="id"
                                   required
                                   pattern="[0-9]+"
                                   placeholder="0111161"
                                   class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                   value="">
                        </div>
                        <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">
                            <i class="fa fa-plus mr-2"></i>Add Movie
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Enter only the numeric part of the IMDB ID (without 'tt' prefix)
                    </p>
                </div>

                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fa fa-lightbulb-o mr-1"></i>How it works:
                    </h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                        <li>The system will fetch movie information from TMDB (The Movie Database)</li>
                        <li>Movie details, posters, and backdrops will be automatically downloaded</li>
                        <li>Associated releases will be linked to the movie information</li>
                    </ul>
                </div>

                <div class="mt-6 flex gap-3">
                    <a href="{{ url('admin/movie-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fa fa-arrow-left mr-2"></i>Back to Movie List
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

