@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-sm transition-colors duration-200">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site->home_link ?? '/') }}" class="text-gray-700 dark:text-gray-300 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-400 inline-flex items-center">
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
                                <a href="{{ route('myshows') }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300" title="Manage your shows">Manage My Shows</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ url('/rss/myshows?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300" title="RSS Feed">RSS Feed</a>
                            </div>
                        @endif

                        @if(isset($covgroup) && $covgroup != '')
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-gray-600 dark:text-gray-400 dark:text-gray-400">View:</span>
                                <a href="{{ url('/' . $covgroup . '/' . $category) }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300">Covers</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200">List</span>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <small class="text-gray-600 dark:text-gray-400 dark:text-gray-400">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(auth()->user()->hasRole('Admin'))
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 dark:hover:bg-red-800 transition text-sm" title="Delete">
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
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">Sort by:</label>
                            <select class="border border-gray-300 dark:border-gray-600 dark:border-gray-600 bg-white dark:bg-gray-800 dark:bg-gray-800 text-gray-900 dark:text-gray-100 dark:text-gray-100 rounded px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" onchange="window.location.href=this.value">
                                <option value="{{ $orderbyposted ?? '#' }}" {{ request('ob') == 'posted_desc' ? 'selected' : '' }}>Posted</option>
                                <option value="{{ $orderbyname ?? '#' }}" {{ request('ob') == 'name_asc' ? 'selected' : '' }}>Name</option>
                                <option value="{{ $orderbysize ?? '#' }}" {{ request('ob') == 'size_desc' ? 'selected' : '' }}>Size</option>
                                <option value="{{ $orderbyfiles ?? '#' }}" {{ request('ob') == 'files_desc' ? 'selected' : '' }}>Files</option>
                                <option value="{{ $orderbystats ?? '#' }}" {{ request('ob') == 'stats_desc' ? 'selected' : '' }}>Stats</option>
                            </select>
                        </div>
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
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Posted</th>
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
                                        @if($result->cover ?? false)
                                            <img src="{{ $result->cover }}" class="w-12 h-16 object-cover rounded mr-3" alt="Cover">
                                        @endif
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="{{ url('/details/' . $result->guid) }}" class="text-blue-600 dark:text-blue-400 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 dark:hover:text-blue-300 font-medium">{{ $result->searchname }}</a>
                                                @if(!empty($result->failed) && $result->failed > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200"
                                                          title="{{ $result->failed }} user(s) reported download failure">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $result->failed }})
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
                                                @if(isset($result->videos_id) && $result->videos_id > 0)
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
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                @if($result->group_name)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 dark:bg-gray-700 text-gray-700 dark:text-gray-300 dark:text-gray-300">
                                                        <i class="fas fa-users mr-1"></i> {{ $result->group_name }}
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
                                    {{ \Carbon\Carbon::parse($result->postdate)->diffForHumans() }}
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
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb px-2 py-1 bg-green-600 dark:bg-green-700 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 dark:hover:bg-green-800 transition text-sm" title="Download NZB" onclick="showToast('Downloading NZB...', 'success')">
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

    <!-- MediaInfo Modal -->
    <div id="mediainfoModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4" style="display: none; z-index: 9999 !important;">
        <div class="relative max-w-4xl w-full bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i> Media Information
                </h3>
                <button type="button" onclick="closeMediainfoModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 text-2xl font-bold">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="mediainfoContent" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 80px);">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>
                    <p class="text-gray-600 dark:text-gray-400 dark:text-gray-400 mt-2">Loading media information...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- File List Modal -->
    <div id="filelistModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4" style="display: none; z-index: 9999 !important;">
        <div class="relative max-w-4xl w-full bg-white dark:bg-gray-800 dark:bg-gray-800 rounded-lg shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 flex items-center">
                    <i class="fas fa-file-archive mr-2 text-green-600 dark:text-green-400"></i> File List
                </h3>
                <button type="button" onclick="closeFilelistModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 text-2xl font-bold">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="filelistContent" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 80px);">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-green-600 dark:text-green-400"></i>
                    <p class="text-gray-600 dark:text-gray-400 dark:text-gray-400 mt-2">Loading file list...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

function showPreviewImage(guid, type = 'preview') {
    const modal = document.getElementById('previewModal');
    const img = document.getElementById('previewImage');
    const error = document.getElementById('previewError');
    const title = document.getElementById('previewTitle');

    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    title.textContent = type === 'sample' ? 'Sample Image' : 'Preview Image';

    const imageUrl = '/covers/' + type + '/' + guid + '.jpg';
    img.src = imageUrl;
    error.classList.add('hidden');
    img.style.display = 'block';

    img.onerror = function() {
        error.textContent = (type === 'sample' ? 'Sample' : 'Preview') + ' image not available';
        error.classList.remove('hidden');
        img.style.display = 'none';
    };

    img.onload = function() {
        img.style.display = 'block';
    };
}

function closeMediainfoModal() {
    const modal = document.getElementById('mediainfoModal');
    modal.style.display = 'none';
}

function closeFilelistModal() {
    const modal = document.getElementById('filelistModal');
    modal.style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showFilelist(guid) {
    let modal = document.getElementById('filelistModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const content = document.getElementById('filelistContent');
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '99999';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl text-green-600"></i>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Loading file list...</p>
        </div>
    `;

    const apiUrl = '/api/release/' + guid + '/filelist';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load file list');
            }
            return response.json();
        })
        .then(data => {
            if (!data.files || data.files.length === 0) {
                content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No files available</p>';
                return;
            }

            let html = '<div class="space-y-4">';
            html += `
                <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 mb-2">${escapeHtml(data.release.searchname)}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400">Total Files: ${data.total}</p>
                </div>
            `;

            html += `
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-800 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">File Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 dark:text-gray-300 uppercase tracking-wider">Size</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            `;

            data.files.forEach((file, index) => {
                const rowClass = index % 2 === 0 ? 'bg-white dark:bg-gray-800 dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900 dark:bg-gray-750';
                const fileName = file.title || file.name || 'Unknown';
                const fileSize = file.size ? formatFileSize(file.size) : 'N/A';

                html += `
                    <tr class="${rowClass} hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100 break-all">${escapeHtml(fileName)}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 dark:text-gray-400 whitespace-nowrap">${fileSize}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            html += '</div>';
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                    <p class="text-red-600 mt-2">${error.message}</p>
                </div>
            `;
        });
}


function showMediainfo(releaseId) {
    let modal = document.getElementById('mediainfoModal');

    // If modal doesn't have body as parent, move it there
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const content = document.getElementById('mediainfoContent');

    // Show modal with loading state
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '99999';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Loading media information...</p>
        </div>
    `;

    // Fetch mediainfo data
    const apiUrl = '/api/release/' + releaseId + '/mediainfo';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load media information');
            }
            return response.json();
        })
        .then(data => {
            if (!data.video && !data.audio && !data.subs) {
                content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No media information available</p>';
                return;
            }

            let html = '<div class="space-y-6">';

            // Video information
            if (data.video) {
                html += `
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-video mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i> Video Information
                        </h4>
                        <dl class="grid grid-cols-2 gap-3">
                `;

                if (data.video.containerformat) {
                    html += `
                        <div>
                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Container</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.video.containerformat}</dd>
                        </div>
                    `;
                }
                if (data.video.videocodec) {
                    html += `
                        <div>
                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Codec</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.video.videocodec}</dd>
                        </div>
                    `;
                }
                if (data.video.videowidth && data.video.videoheight) {
                    html += `
                        <div>
                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Resolution</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.video.videowidth}x${data.video.videoheight}</dd>
                        </div>
                    `;
                }
                if (data.video.videoaspect) {
                    html += `
                        <div>
                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Aspect Ratio</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.video.videoaspect}</dd>
                        </div>
                    `;
                }
                if (data.video.videoframerate) {
                    html += `
                        <div>
                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Frame Rate</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.video.videoframerate} fps</dd>
                        </div>
                    `;
                }
                if (data.video.videoduration) {
                    // videoduration is in milliseconds, convert to minutes
                    const durationMs = parseInt(data.video.videoduration);
                    if (!isNaN(durationMs) && durationMs > 0) {
                        const minutes = Math.round(durationMs / 1000 / 60);
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Duration</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${minutes} minutes</dd>
                            </div>
                        `;
                    }
                }

                html += '</dl></div>';
            }

            // Audio information
            if (data.audio && data.audio.length > 0) {
                html += `
                    <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-volume-up mr-2 text-green-600 dark:text-green-400"></i> Audio Information
                        </h4>
                `;

                data.audio.forEach((audio, index) => {
                    if (index > 0) html += '<hr class="my-3 border-gray-200 dark:border-gray-700 dark:border-gray-700">';
                    html += '<dl class="grid grid-cols-2 gap-3">';

                    if (audio.audioformat) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Format</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${audio.audioformat}</dd>
                            </div>
                        `;
                    }
                    if (audio.audiochannels) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Channels</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${audio.audiochannels}</dd>
                            </div>
                        `;
                    }
                    if (audio.audiobitrate) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Bit Rate</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${audio.audiobitrate}</dd>
                            </div>
                        `;
                    }
                    if (audio.audiolanguage) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Language</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${audio.audiolanguage}</dd>
                            </div>
                        `;
                    }
                    if (audio.audiosamplerate) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 dark:text-gray-400">Sample Rate</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${audio.audiosamplerate}</dd>
                            </div>
                        `;
                    }

                    html += '</dl>';
                });

                html += '</div>';
            }

            // Subtitle information
            if (data.subs) {
                html += `
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-lg p-4">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 dark:text-gray-200 mb-3 flex items-center">
                            <i class="fas fa-closed-captioning mr-2 text-purple-600 dark:text-purple-400"></i> Subtitles
                        </h4>
                        <p class="text-sm text-gray-900 dark:text-gray-100 dark:text-gray-100">${data.subs}</p>
                    </div>
                `;
            }

            html += '</div>';
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                    <p class="text-red-600 mt-2">${error.message}</p>
                </div>
            `;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Preview and Sample badge click handlers
    document.addEventListener('click', function(e) {
        const previewBadge = e.target.closest('.preview-badge');
        if (previewBadge) {
            e.preventDefault();
            const guid = previewBadge.dataset.guid;
            showPreviewImage(guid, 'preview');
        }

        const sampleBadge = e.target.closest('.sample-badge');
        if (sampleBadge) {
            e.preventDefault();
            const guid = sampleBadge.dataset.guid;
            showPreviewImage(guid, 'sample');
        }

        // Mediainfo badge click handlers
        const mediainfoBadge = e.target.closest('.mediainfo-badge');
        if (mediainfoBadge) {
            e.preventDefault();
            const releaseId = mediainfoBadge.dataset.releaseId;
            showMediainfo(releaseId);
        }

        // File list badge click handlers
        const filelistBadge = e.target.closest('.filelist-badge');
        if (filelistBadge) {
            e.preventDefault();
            const guid = filelistBadge.dataset.guid;
            showFilelist(guid);
        }
    });

    // Close modal on background click
    document.getElementById('previewModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePreviewModal();
        }
    });

    document.getElementById('mediainfoModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMediainfoModal();
        }
    });

    document.getElementById('filelistModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeFilelistModal();
        }
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePreviewModal();
            closeMediainfoModal();
            closeFilelistModal();
        }
    });

    // Select all checkbox functionality
    document.getElementById('chkSelectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.chkRelease');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
    // Multi-operations download
    document.querySelector('.nzb_multi_operations_download')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            return;
        }
        selected.forEach(guid => {
            window.open('/getnzb/' + guid, '_blank');
        });
        showToast(`Downloading ${selected.length} NZB${selected.length > 1 ? 's' : ''}...`, 'success');
    });

    // Multi-operations cart
    document.querySelector('.nzb_multi_operations_cart')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            showToast('Please select at least one release', 'error');
            return;
        }
        // Add to cart via AJAX
        fetch('/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: selected.join(',') })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  showToast(data.message || `Added ${selected.length} item${selected.length > 1 ? 's' : ''} to cart`, 'success');
              } else {
                  showToast('Failed to add items to cart', 'error');
              }
          })
          .catch(error => {
              showToast('An error occurred', 'error');
          });
    });

    // Individual add to cart buttons
    document.addEventListener('click', function(e) {
        const cartBtn = e.target.closest('.add-to-cart');

        if (cartBtn) {
            e.preventDefault();

            const guid = cartBtn.dataset.guid;
            const iconElement = cartBtn.querySelector('.icon_cart');

            if (!guid) {
                console.error('No GUID found for cart item');
                return;
            }

            // Prevent double-clicking
            if (iconElement && iconElement.classList.contains('icon_cart_clicked')) {
                return;
            }

            // Send AJAX request to add item to cart
            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: guid })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Visual feedback
                    if (iconElement) {
                        iconElement.classList.remove('fa-shopping-basket');
                        iconElement.classList.add('fa-check', 'icon_cart_clicked');

                        // Reset icon after 2 seconds
                        setTimeout(() => {
                            iconElement.classList.remove('fa-check', 'icon_cart_clicked');
                            iconElement.classList.add('fa-shopping-basket');
                        }, 2000);
                    }

                    // Show success notification
                    showToast('Added to cart successfully!', 'success');
                } else {
                    showToast('Failed to add item to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showToast('An error occurred', 'error');
            });
        }
    });
});
</script>
@endpush

