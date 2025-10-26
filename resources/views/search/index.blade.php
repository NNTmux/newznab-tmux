@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Search Releases</h1>
        <p class="text-gray-600">Find exactly what you're looking for</p>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('search') }}" class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Search Query -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Terms</label>
                <input type="text"
                       id="search"
                       name="search"
                       value="{{ request('search') }}"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                       placeholder="Enter search terms...">
            </div>

            <!-- Category -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <select id="category"
                        name="t"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">All Categories</option>
                    @if(isset($parentcatlist))
                        @foreach($parentcatlist as $parentcat)
                            @php
                                $parentTitle = is_object($parentcat) ? $parentcat->title : ($parentcat['title'] ?? 'Category');
                                $subcategories = is_object($parentcat) ? $parentcat->categories : ($parentcat['categories'] ?? []);
                            @endphp
                            <optgroup label="{{ $parentTitle }}">
                                @foreach($subcategories as $subcat)
                                    @php
                                        $subcatId = is_object($subcat) ? $subcat->id : ($subcat['id'] ?? '');
                                        $subcatTitle = is_object($subcat) ? $subcat->title : ($subcat['title'] ?? '');
                                    @endphp
                                    <option value="{{ $subcatId }}" {{ request('t') == $subcatId ? 'selected' : '' }}>
                                        {{ $subcatTitle }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @elseif(isset($catlist))
                        @foreach($catlist as $catId => $catTitle)
                            <option value="{{ $catId }}" {{ request('t') == $catId ? 'selected' : '' }}>
                                {{ $catTitle }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        @if(request('search_type') == 'adv')
            <!-- Hidden field to maintain advanced search mode -->
            <input type="hidden" name="search_type" value="adv">
            <!-- Advanced Search Options -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced Options</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="group" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Usenet Group</label>
                        <input type="text" id="group" name="group" value="{{ request('group') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               placeholder="e.g., alt.binaries.teevee">
                    </div>

                    <div>
                        <label for="minage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Age (days)</label>
                        <input type="number" id="minage" name="minage" value="{{ request('minage') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="maxage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Age (days)</label>
                        <input type="number" id="maxage" name="maxage" value="{{ request('maxage') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="minsize" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Size (MB)</label>
                        <input type="number" id="minsize" name="minsize" value="{{ request('minsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="maxsize" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Size (MB)</label>
                        <input type="number" id="maxsize" name="maxsize" value="{{ request('maxsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>
                </div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2">
            <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition inline-flex items-center justify-center font-semibold">
                <i class="fas fa-search mr-2"></i> Search
            </button>
            <a href="{{ url('/search?search_type=adv') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition inline-flex items-center justify-center">
                <i class="fas fa-sliders-h mr-2"></i> Advanced Search
            </a>
            <a href="{{ route('search') }}" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-100 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-700 transition justify-center inline-flex">
                Clear
            </a>
        </div>
    </form>

    <!-- Search Results -->
    @if(isset($results) && ((is_array($results) && count($results) > 0) || (is_object($results) && $results->count() > 0)))
        <form id="nzb_multi_operations_form" method="get">
            <!-- Multi-operations toolbar -->
            <div class="mb-4 bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                        <div class="flex gap-1">
                            <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                <i class="fa fa-cloud-download"></i>
                            </button>
                            <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                <i class="fa fa-shopping-basket"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">
                            Search Results ({{ is_object($results) ? $results->total() : count($results) }} found)
                        </h2>
                        @if(is_object($results))
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Page {{ $results->currentPage() }} of {{ $results->lastPage() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sort Options -->
            <div class="mb-4 bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <span class="block sm:inline font-medium text-gray-700 dark:text-gray-300 mb-2 sm:mb-0 sm:mr-2 text-sm">Sort by:</span>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    @php
                        $currentSort = request('ob', 'posted_desc');
                        $sortOptions = [
                            'posted_desc' => ['label' => 'Posted Date ↓', 'icon' => 'fa-calendar'],
                            'posted_asc' => ['label' => 'Posted Date ↑', 'icon' => 'fa-calendar'],
                            'size_desc' => ['label' => 'Size ↓', 'icon' => 'fa-hdd'],
                            'size_asc' => ['label' => 'Size ↑', 'icon' => 'fa-hdd'],
                            'name_asc' => ['label' => 'Name ↑', 'icon' => 'fa-font'],
                            'name_desc' => ['label' => 'Name ↓', 'icon' => 'fa-font'],
                        ];
                        $queryParams = request()->except('ob');
                    @endphp
                    @foreach($sortOptions as $sortKey => $sortData)
                        <a href="{{ route('search', array_merge($queryParams, ['ob' => $sortKey])) }}"
                           class="px-3 py-1.5 rounded {{ $currentSort === $sortKey ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }} transition border border-gray-300 dark:border-gray-600">
                            <i class="fas {{ $sortData['icon'] }} mr-1"></i>{{ $sortData['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Desktop Table View (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-3 text-left">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" id="chkSelectAll">
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Added</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Size</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Files</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Stats</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($results as $result)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" name="release[]" value="{{ $result->guid }}">
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <a href="{{ url('/details/' . $result->guid) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium break-words break-all">{{ $result->searchname }}</a>
                                            @if(isset($result->haspreview) && $result->haspreview == 1)
                                                <button type="button"
                                                        class="preview-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                                        data-guid="{{ $result->guid }}"
                                                        title="View preview image">
                                                    <i class="fas fa-image mr-1"></i> Preview
                                                </button>
                                            @endif
                                            @if(isset($result->jpgstatus) && $result->jpgstatus == 1)
                                                <button type="button"
                                                        class="sample-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-800 transition cursor-pointer"
                                                        data-guid="{{ $result->guid }}"
                                                        title="View sample image">
                                                    <i class="fas fa-images mr-1"></i> Sample
                                                </button>
                                            @endif
                                            @if(isset($result->nfostatus) && $result->nfostatus == 1)
                                                <button type="button"
                                                        class="nfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800 transition cursor-pointer"
                                                        data-guid="{{ $result->guid }}"
                                                        title="View NFO file">
                                                    <i class="fas fa-file-alt mr-1"></i> NFO
                                                </button>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-2">
                                            @if($result->group_name)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                    <i class="fas fa-users mr-1"></i> {{ $result->group_name }}
                                                </span>
                                            @endif
                                            @if(!empty($result->postdate))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    <i class="fas fa-calendar mr-1"></i> Posted: {{ userDate($result->postdate, 'M d, Y H:i') }}
                                                </span>
                                            @endif
                                            @if(!empty($result->fromname))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono">
                                                    <i class="fas fa-user mr-1"></i> {{ $result->fromname }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">
                                    {{ userDateDiffForHumans($result->adddate) }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ number_format($result->size / 1073741824, 2) }} GB
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $result->totalpart ?? 0 }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400"></i> {{ $result->grabs ?? 0 }}</span>
                                        <span title="Comments"><i class="fas fa-comment text-blue-600 dark:text-blue-400"></i> {{ $result->comments ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb px-2 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZB">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="View Details">
                                            <i class="fa fa-info"></i>
                                        </a>
                                        <a href="#" class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm" data-guid="{{ $result->guid }}" title="Add to Cart">
                                            <i class="icon_cart fa fa-shopping-basket"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (visible on mobile only) -->
            <div class="md:hidden space-y-3">
                @foreach($results as $result)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700 mt-1" name="release[]" value="{{ $result->guid }}">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-2">
                                    <a href="{{ url('/details/' . $result->guid) }}" class="text-lg font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 break-words break-all">
                                        {{ $result->searchname }}
                                    </a>
                                    @if(isset($result->haspreview) && $result->haspreview == 1)
                                        <button type="button"
                                                class="preview-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                                data-guid="{{ $result->guid }}"
                                                title="View preview image">
                                            <i class="fas fa-image mr-1"></i> Preview
                                        </button>
                                    @endif
                                    @if(isset($result->jpgstatus) && $result->jpgstatus == 1)
                                        <button type="button"
                                                class="sample-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 hover:bg-green-200 dark:hover:bg-green-800 transition cursor-pointer"
                                                data-guid="{{ $result->guid }}"
                                                title="View sample image">
                                            <i class="fas fa-images mr-1"></i> Sample
                                        </button>
                                    @endif
                                    @if(isset($result->nfostatus) && $result->nfostatus == 1)
                                        <button type="button"
                                                class="nfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800 transition cursor-pointer"
                                                data-guid="{{ $result->guid }}"
                                                title="View NFO file">
                                            <i class="fas fa-file-alt mr-1"></i> NFO
                                        </button>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                    <span><i class="fas fa-clock mr-1"></i>{{ userDateDiffForHumans($result->postdate) }}</span>
                                    <span><i class="fas fa-hdd mr-1"></i>{{ number_format($result->size / 1073741824, 2) }} GB</span>
                                    <span><i class="fas fa-file mr-1"></i>{{ $result->totalpart ?? 0 }} files</span>
                                    <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400 mr-1"></i>{{ $result->grabs ?? 0 }}</span>
                                    <span title="Comments"><i class="fas fa-comment text-blue-600 dark:text-blue-400 mr-1"></i>{{ $result->comments ?? 0 }}</span>
                                    @if($result->group_name)
                                        <span><i class="fas fa-users mr-1"></i>{{ $result->group_name }}</span>
                                    @endif
                                    @if(!empty($result->fromname))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono text-xs">
                                            <i class="fas fa-user mr-1"></i>{{ $result->fromname }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 flex gap-1 flex-wrap">
                                    <a href="{{ url('/getnzb/' . $result->guid) }}"
                                       class="download-nzb px-2 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm"
                                       title="Download NZB">
                                        <i class="fa fa-download"></i>
                                    </a>
                                    <a href="{{ url('/details/' . $result->guid) }}"
                                       class="px-2 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm"
                                       title="View Details">
                                        <i class="fa fa-info"></i>
                                    </a>
                                    <a href="#"
                                       class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm"
                                       data-guid="{{ $result->guid }}"
                                       title="Add to Cart">
                                        <i class="icon_cart fa fa-shopping-basket"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if(is_object($results) && method_exists($results, 'links'))
                <div class="mt-6">
                    {{ $results->appends(request()->query())->links() }}
                </div>
            @endif
        </form>
    @elseif(request()->has('search'))
        <div class="text-center py-12">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No results found</h3>
            <p class="text-gray-500">Try adjusting your search terms or using different filters.</p>
        </div>
    @else
        <div class="text-center py-12">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">Start Your Search</h3>
            <p class="text-gray-500">Enter search terms above to find releases.</p>
        </div>
    @endif

    <!-- Preview/Sample Image Modal -->
    <div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-75 items-center justify-center p-4" style="display: none; z-index: 9999 !important;">
        <div class="relative max-w-4xl w-full">
            <button type="button" onclick="closePreviewModal()" class="absolute top-4 right-4 text-white hover:text-gray-300 text-3xl font-bold z-10">
                <i class="fas fa-times"></i>
            </button>
            <div class="text-center mb-2">
                <h3 id="previewTitle" class="text-white text-lg font-semibold"></h3>
            </div>
            <img id="previewImage" src="" alt="Preview" class="max-w-full max-h-[90vh] mx-auto rounded-lg shadow-2xl">
            <div class="text-center mt-4">
                <p id="previewError" class="text-red-400 hidden"></p>
            </div>
        </div>
    </div>

    <!-- NFO Modal -->
    @include('partials.nfo-modal')
</div>
@endsection

