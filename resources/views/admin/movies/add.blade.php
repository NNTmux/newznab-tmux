@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-film" />
    <x-admin.card>




        <!-- Info Alert -->
        <div class="px-6 py-4 bg-info-50 dark:bg-info-900 border-b border-info-100">
            <div class="flex">
                <i aria-hidden="true" class="fas fa-info-circle text-info-500 text-xl mr-3"></i>
                <div class="text-sm text-info-700 dark:text-info-300">
                    <p class="mb-2">
                        Enter an IMDB ID (numeric only, without the 'tt' prefix) to add movie information to the database.
                    </p>
                    <p class="text-xs text-info-600 dark:text-info-400">
                        Example: For <code class="px-1 bg-info-100 dark:bg-info-800 rounded">https://www.imdb.com/title/tt0111161/</code>, enter <code class="px-1 bg-info-100 dark:bg-info-800 rounded">0111161</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Movie Form -->
        <form method="POST" action="{{ route('admin.movie-add') }}" class="p-6">
            @csrf
            <x-form-errors class="mb-6" />
            <div class="max-w-2xl">
                <div class="mb-6">
                    <label for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        IMDB ID <span class="text-danger-500">*</span>
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
                                   class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                   value="{{ old('id') }}">
                        </div>
                        <button type="submit" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover whitespace-nowrap">
                            <i aria-hidden="true" class="fas fa-plus mr-2"></i>Add Movie
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Enter only the numeric part of the IMDB ID (without 'tt' prefix)
                    </p>
                </div>

                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        <i aria-hidden="true" class="fa-regular fa-lightbulb mr-1"></i>How it works:
                    </h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                        <li>The system will fetch movie information from TMDB (The Movie Database)</li>
                        <li>Movie details, posters, and backdrops will be automatically downloaded</li>
                        <li>Associated releases will be linked to the movie information</li>
                    </ul>
                </div>

                <div class="mt-6 flex gap-3">
                    <a href="{{ url('admin/movie-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i>Back to Movie List
                    </a>
                </div>
            </div>
        </form>
    </x-admin.card>
</div>
@endsection
