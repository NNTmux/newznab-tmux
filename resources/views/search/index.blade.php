@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Search Releases</h1>
        <p class="text-gray-600">Find exactly what you're looking for</p>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('search') }}" class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Search Query -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Terms</label>
                <input type="text"
                       id="search"
                       name="search"
                       value="{{ request('search') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter search terms...">
            </div>

            <!-- Category -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select id="category"
                        name="t"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Categories</option>
                    @if(isset($parentcatlist))
                        @foreach($parentcatlist as $parentcat)
                            <optgroup label="{{ $parentcat->title }}">
                                @foreach($parentcat->categories as $subcat)
                                    <option value="{{ $subcat->id }}" {{ request('t') == $subcat->id ? 'selected' : '' }}>
                                        {{ $subcat->title }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        @if(request('search_type') == 'adv')
            <!-- Advanced Search Options -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Advanced Options</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="group" class="block text-sm font-medium text-gray-700 mb-2">Usenet Group</label>
                        <input type="text" id="group" name="group" value="{{ request('group') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., alt.binaries.teevee">
                    </div>

                    <div>
                        <label for="minage" class="block text-sm font-medium text-gray-700 mb-2">Min Age (days)</label>
                        <input type="number" id="minage" name="minage" value="{{ request('minage') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               min="0">
                    </div>

                    <div>
                        <label for="maxage" class="block text-sm font-medium text-gray-700 mb-2">Max Age (days)</label>
                        <input type="number" id="maxage" name="maxage" value="{{ request('maxage') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               min="0">
                    </div>

                    <div>
                        <label for="minsize" class="block text-sm font-medium text-gray-700 mb-2">Min Size (MB)</label>
                        <input type="number" id="minsize" name="minsize" value="{{ request('minsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               min="0">
                    </div>

                    <div>
                        <label for="maxsize" class="block text-sm font-medium text-gray-700 mb-2">Max Size (MB)</label>
                        <input type="number" id="maxsize" name="maxsize" value="{{ request('maxsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               min="0">
                    </div>
                </div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition inline-flex items-center">
                <i class="fas fa-search mr-2"></i> Search
            </button>
            <a href="{{ url('/search?search_type=adv') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition inline-flex items-center">
                <i class="fas fa-sliders-h mr-2"></i> Advanced Search
            </a>
            <a href="{{ route('search') }}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                Clear
            </a>
        </div>
    </form>

    <!-- Search Results -->
    @if(isset($results) && $results->count() > 0)
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">
                    Search Results ({{ $results->total() }} found)
                </h2>
                <div class="text-sm text-gray-600">
                    Page {{ $results->currentPage() }} of {{ $results->lastPage() }}
                </div>
            </div>

            <div class="space-y-3">
                @foreach($results as $result)
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <a href="{{ url('/details/' . $result->guid) }}" class="text-lg font-medium text-blue-600 hover:text-blue-800">
                                    {{ $result->searchname }}
                                </a>
                                <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-600">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                    <span><i class="fas fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($result->postdate)->diffForHumans() }}</span>
                                    <span><i class="fas fa-hdd mr-1"></i>{{ number_format($result->size / 1073741824, 2) }} GB</span>
                                    <span><i class="fas fa-file mr-1"></i>{{ $result->totalpart ?? 0 }} files</span>
                                    @if($result->group_name)
                                        <span><i class="fas fa-users mr-1"></i>{{ $result->group_name }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4 flex gap-2">
                                <a href="{{ url('/getnzb/' . $result->guid) }}"
                                   class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm"
                                   title="Download NZB">
                                    <i class="fa fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $results->appends(request()->query())->links() }}
            </div>
        </div>
    @elseif(request()->has('search'))
        <div class="text-center py-12">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No results found</h3>
            <p class="text-gray-500">Try adjusting your search terms or using different filters.</p>
        </div>
    @else
        <div class="text-center py-12">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 mb-2">Start Your Search</h3>
            <p class="text-gray-500">Enter search terms above to find releases.</p>
        </div>
    @endif
</div>
@endsection

