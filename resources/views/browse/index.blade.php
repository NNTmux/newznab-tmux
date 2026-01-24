@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-sm transition-colors duration-200">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site['home_link'] ?? '/') }}" class="text-gray-700 dark:text-gray-300 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-400 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                @if(isset($parentcat) && $parentcat != '')
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 mx-2"></i>
                            <a href="{{ url('/browse/' . ($parentcat == 'music' ? 'Audio' : $parentcat)) }}" class="text-gray-700 dark:text-gray-300 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-400">{{ $parentcat }}</a>
                        </div>
                    </li>
                    @if(isset($catname) && $catname != '' && $catname != 'all')
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 mx-2"></i>
                                <span class="text-gray-500 dark:text-gray-400">{{ $catname }}</span>
                            </div>
                        </li>
                    @endif
                @else
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 mx-2"></i>
                            <span class="text-gray-500 dark:text-gray-400">Browse / {{ $catname ?? 'All' }}</span>
                        </div>
                    </li>
                @endif
            </ol>
        </nav>
    </div>

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get">
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left Section -->
                    <div class="space-y-3">
                        @if(isset($shows))
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('series') }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300" title="View available TV series">Series List</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ route('trending-tv') }}" class="text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-300" title="View trending TV shows"><i class="fas fa-fire mr-1"></i>Trending TV</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ route('myshows') }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300" title="Manage your shows">Manage My Shows</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ url('/rss/myshows?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300" title="RSS Feed">RSS Feed</a>
                            </div>
                        @endif

                        @if(isset($covgroup) && $covgroup != '' || isset($shows) && $shows)
                            <x-view-toggle
                                current-view="list"
                                :covgroup="$covgroup ?? null"
                                :category="$category ?? null"
                                :parentcat="$parentcat ?? null"
                                :shows="$shows ?? false"
                            />
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(auth()->check() && auth()->user()->hasRole('Admin'))
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 transition text-sm" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Center Section - Pagination Info -->
                    <div class="flex items-center justify-center">
                        <div class="text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">
                            Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
                        </div>
                    </div>

                    <!-- Right Section - Sort Options -->
                    <div class="flex items-center justify-end">
                        <x-sort-dropdown />
                    </div>
                </div>
            </div>

            <!-- Top Pagination -->
            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                {{ $results->links() }}
            </div>

            <!-- Results Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-800 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-3 text-left">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:border-gray-600 text-blue-600 dark:text-blue-400 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" id="chkSelectAll">
                            </th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Category</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Added</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Size</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Files</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Stats</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($results as $result)
                            <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700 transition">
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 dark:border-gray-600 text-blue-600 dark:text-blue-400 dark:text-blue-500 focus:ring-blue-500 dark:focus:ring-blue-400 dark:bg-gray-700" name="release[]" value="{{ $result->guid }}">
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex items-start">
                                        @php
                                            $showThumbnails = request()->query('thumbs', '0') === '1';
                                            $coverUrl = ($result->cover ?? false) ? $result->cover : ($showThumbnails ? getReleaseCover($result) : null);
                                            $hasValidCover = $coverUrl && !str_contains($coverUrl, 'no-cover.png');
                                        @endphp
                                        @if($hasValidCover)
                                            <a href="{{ url('/details/' . $result->guid) }}" class="flex-shrink-0">
                                                <img src="{{ $coverUrl }}" class="w-12 h-16 object-cover rounded mr-3 shadow-sm hover:shadow-md transition" alt="Cover" loading="lazy">
                                            </a>
                                        @endif
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="{{ url('/details/' . $result->guid) }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300 font-medium break-words break-all">{{ $result->searchname }}</a>
                                                @if(!empty($result->failed_count) && $result->failed_count > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200"
                                                          title="{{ $result->failed_count }} user(s) reported download failure">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $result->failed_count }})
                                                    </span>
                                                @endif
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
                                                @if(!empty($result->videos_id) && (int)$result->videos_id > 0)
                                                    <a href="{{ url('/series/' . $result->videos_id) }}"
                                                       class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 hover:bg-indigo-200 dark:hover:bg-indigo-800 transition"
                                                       title="View full series">
                                                        <i class="fas fa-tv mr-1"></i> View Series
                                                    </a>
                                                @endif
                                                @if(isset($result->reid) && $result->reid != null)
                                                    <button type="button"
                                                            class="mediainfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800 transition cursor-pointer"
                                                            data-release-id="{{ $result->id }}"
                                                            title="View media info">
                                                        <i class="fas fa-info-circle mr-1"></i> Media Info
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
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 dark:bg-gray-700 text-gray-700 dark:text-gray-300 dark:text-gray-300">
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
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">
                                    {{ $result->size_formatted ?? number_format($result->size / 1073741824, 2) . ' GB' }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">
                                    @if($result->totalpart > 0)
                                        <button type="button"
                                                class="filelist-badge text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300 font-medium cursor-pointer hover:underline"
                                                data-guid="{{ $result->guid }}"
                                                title="View file list">
                                            {{ $result->totalpart ?? 0 }}
                                        </button>
                                    @else
                                        {{ $result->totalpart ?? 0 }}
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400"></i> {{ $result->grabs ?? 0 }}</span>
                                        <span title="Comments"><i class="fas fa-comment text-blue-600 dark:text-blue-400 dark:text-blue-400"></i> {{ $result->comments ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb px-2 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZB">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-blue-600 dark:bg-blue-700 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 dark:hover:bg-blue-800 transition text-sm" title="View Details">
                                            <i class="fa fa-info"></i>
                                        </a>
                                        <a href="#" class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 dark:bg-gray-600 text-gray-700 dark:text-gray-300 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition text-sm" data-guid="{{ $result->guid }}" title="Add to Cart">
                                            <i class="icon_cart fa fa-shopping-basket"></i>
                                        </a>
                                        @if(!empty($result->imdbid) && $result->imdbid != '0' && $result->imdbid != 0 && $result->imdbid != '0000000')
                                            <a href="{{ url('/mymovies?id=add&imdb=' . $result->imdbid) }}"
                                               class="px-2 py-1 bg-purple-600 dark:bg-purple-700 text-white rounded hover:bg-purple-700 dark:hover:bg-purple-800 transition text-sm"
                                               title="Add to My Movies">
                                                <i class="fa fa-film"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Bottom Pagination -->
            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 dark:border-gray-700">
                {{ $results->links() }}
            </div>
        </form>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-search text-6xl text-gray-300 dark:text-gray-600 dark:text-gray-400 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 mb-2">No releases found</h3>
            <p class="text-gray-500 dark:text-gray-400">Try adjusting your search criteria or browse other categories.</p>
        </div>
    @endif

    <!-- Preview/Sample Image Modal -->
    <div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-75 items-center justify-center p-4 preview-modal-hidden">
        <div class="relative max-w-4xl w-full">
            <button type="button" data-close-preview-modal class="absolute top-4 right-4 text-white hover:text-gray-300 text-3xl font-bold z-10">
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

    <!-- MediaInfo Modal -->
    <div id="mediainfoModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 modal-hidden modal-z-index">
        <div class="relative max-w-4xl w-full bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i> Media Information
                </h3>
                <button type="button" data-close-mediainfo-modal class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 text-2xl font-bold">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="mediainfoContent" class="p-6 overflow-y-auto modal-content-scroll">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>
                    <p class="text-gray-600 dark:text-gray-400 dark:text-gray-400 mt-2">Loading media information...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- File List Modal -->
    <div id="filelistModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 modal-hidden modal-z-index">
        <div class="relative max-w-4xl w-full bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 flex items-center">
                    <i class="fas fa-file-archive mr-2 text-green-600 dark:text-green-400"></i> File List
                </h3>
                <button type="button" data-close-filelist-modal class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 text-2xl font-bold">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="filelistContent" class="p-6 overflow-y-auto modal-content-scroll">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-green-600 dark:text-green-400"></i>
                    <p class="text-gray-600 dark:text-gray-400 dark:text-gray-400 mt-2">Loading file list...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- NFO Modal -->
    @include('partials.nfo-modal')
</div>
@endsection

