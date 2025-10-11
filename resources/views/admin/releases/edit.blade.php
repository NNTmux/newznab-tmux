@extends('layouts.admin')

@section('title', 'Edit Release')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Edit Release</h1>
        <nav class="text-sm text-gray-600 dark:text-gray-400 mt-2">
            <a href="{{ route('admin.index') }}" class="hover:text-blue-600">Dashboard</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <a href="{{ route('admin.release-list') }}" class="hover:text-blue-600">Releases</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span>Edit</span>
        </nav>
    </div>

    @if(session('success'))
        <div class="bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-900 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 dark:bg-red-900/20 border border-red-400 dark:border-red-900 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
        <form action="{{ route('admin.release-edit') }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="id" value="{{ $release->id ?? $release['id'] ?? '' }}">
            <input type="hidden" name="guid" value="{{ $release->guid ?? $release['guid'] ?? '' }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Release Name -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Release Name
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ $release->name ?? $release['name'] ?? '' }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Search Name -->
                <div class="md:col-span-2">
                    <label for="searchname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Search Name
                    </label>
                    <input type="text"
                           id="searchname"
                           name="searchname"
                           value="{{ $release->searchname ?? $release['searchname'] ?? '' }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- From Name -->
                <div class="md:col-span-2">
                    <label for="fromname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        From Name
                    </label>
                    <input type="text"
                           id="fromname"
                           name="fromname"
                           value="{{ $release->fromname ?? $release['fromname'] ?? '' }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Category
                    </label>
                    <select id="category"
                            name="category"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                        @foreach($catlist as $catId => $catTitle)
                            <option value="{{ $catId }}" {{ ($release->categories_id ?? $release['categories_id'] ?? '') == $catId ? 'selected' : '' }}>
                                {{ $catTitle }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Total Parts -->
                <div>
                    <label for="totalpart" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Total Parts
                    </label>
                    <input type="number"
                           id="totalpart"
                           name="totalpart"
                           value="{{ $release->totalpart ?? $release['totalpart'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Grabs -->
                <div>
                    <label for="grabs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Grabs
                    </label>
                    <input type="number"
                           id="grabs"
                           name="grabs"
                           value="{{ $release->grabs ?? $release['grabs'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Size (in bytes) -->
                <div>
                    <label for="size" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Size (bytes)
                    </label>
                    <input type="number"
                           id="size"
                           name="size"
                           value="{{ $release->size ?? $release['size'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Current: {{ number_format(($release->size ?? $release['size'] ?? 0) / 1073741824, 2) }} GB
                    </p>
                </div>

                <!-- Post Date -->
                <div>
                    <label for="postdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Post Date
                    </label>
                    <input type="datetime-local"
                           id="postdate"
                           name="postdate"
                           value="{{ isset($release->postdate) || isset($release['postdate']) ? date('Y-m-d\TH:i', strtotime($release->postdate ?? $release['postdate'])) : '' }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Add Date -->
                <div>
                    <label for="adddate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Add Date
                    </label>
                    <input type="datetime-local"
                           id="adddate"
                           name="adddate"
                           value="{{ isset($release->adddate) || isset($release['adddate']) ? date('Y-m-d\TH:i', strtotime($release->adddate ?? $release['adddate'])) : '' }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- Video ID -->
                <div>
                    <label for="videos_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Video ID
                    </label>
                    <input type="number"
                           id="videos_id"
                           name="videos_id"
                           value="{{ $release->videos_id ?? $release['videos_id'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- TV Episode ID -->
                <div>
                    <label for="tv_episodes_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        TV Episode ID
                    </label>
                    <input type="number"
                           id="tv_episodes_id"
                           name="tv_episodes_id"
                           value="{{ $release->tv_episodes_id ?? $release['tv_episodes_id'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <!-- IMDB ID -->
                <div>
                    <label for="imdbid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        IMDB ID
                    </label>
                    <input type="text"
                           id="imdbid"
                           name="imdbid"
                           value="{{ $release->imdbid ?? $release['imdbid'] ?? '' }}"
                           placeholder="e.g., 0133093"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400">
                </div>

                <!-- AniDB ID -->
                <div>
                    <label for="anidbid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        AniDB ID
                    </label>
                    <input type="number"
                           id="anidbid"
                           name="anidbid"
                           value="{{ $release->anidbid ?? $release['anidbid'] ?? 0 }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
                <a href="{{ route('admin.release-list') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 transition inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <a href="{{ url('/details/' . ($release->guid ?? $release['guid'] ?? '')) }}" class="px-6 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i> View Release
                </a>
            </div>
        </form>
    </div>

    <!-- Additional Info -->
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mt-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Release Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400">GUID</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-mono text-xs mt-1">{{ $release->guid ?? $release['guid'] ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400">Group</dt>
                <dd class="text-gray-900 dark:text-gray-100 mt-1">{{ $release->group_name ?? $release['group_name'] ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400">Password Status</dt>
                <dd class="text-gray-900 dark:text-gray-100 mt-1">
                    @if(($release->passwordstatus ?? $release['passwordstatus'] ?? 0) == 0)
                        <span class="text-green-600 dark:text-green-400"><i class="fas fa-check-circle"></i> None</span>
                    @elseif(($release->passwordstatus ?? $release['passwordstatus'] ?? 0) == 1)
                        <span class="text-red-600 dark:text-red-400"><i class="fas fa-lock"></i> Passworded</span>
                    @else
                        <span class="text-yellow-600 dark:text-yellow-400"><i class="fas fa-question-circle"></i> Unknown</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>
</div>
@endsection

