@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm transition-colors duration-200">
    @php
        $crumbs = [['label' => 'Home', 'url' => url($site['home_link'] ?? '/'), 'icon' => 'fas fa-home']];
        if (isset($parentcat) && $parentcat != '') {
            $crumbs[] = ['label' => $parentcat, 'url' => url('/browse/' . ($parentcat == 'music' ? 'Audio' : $parentcat))];
            if (isset($catname) && $catname != '' && $catname != 'all') {
                $crumbs[] = ['label' => $catname];
            }
        } else {
            $crumbs[] = ['label' => 'Browse / ' . ($catname ?? 'All')];
        }
    @endphp
    <x-breadcrumb :items="$crumbs" />

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get" x-data="releaseMultiOps" data-show-thumbs="{{ request()->query('thumbs', '0') === '1' ? '1' : '0' }}">
            <div class="px-6 py-4 surface-panel-alt border-b">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Left Section -->
                    <div class="space-y-3">
                        @if(isset($shows))
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('series') }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="View available TV series">Series List</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ route('trending-tv') }}" class="text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-300" title="View trending TV shows"><i class="fas fa-fire mr-1"></i>Trending TV</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ route('myshows') }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="Manage your shows">Manage My Shows</a>
                                <span class="text-gray-400 dark:text-gray-500">|</span>
                                <a href="{{ url('/rss/myshows?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="RSS Feed">RSS Feed</a>
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
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(auth()->check() && auth()->user()->hasRole('Admin'))
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition text-sm" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Center Section - Pagination Info -->
                    <div class="flex items-center justify-center">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
                        </div>
                    </div>

                    <!-- Right Section - Sort & Search -->
                    <div class="flex items-center justify-end gap-3">
                        @php
                            $searchPlaceholder = 'Search';
                            if (!empty($parentcat) && $parentcat !== 'All') {
                                $searchPlaceholder .= ' in ' . $parentcat;
                                if (!empty($catname) && $catname !== 'All' && $catname !== 'all') {
                                    $searchPlaceholder .= ' ' . $catname;
                                }
                            }
                            $searchPlaceholder .= '...';
                        @endphp
                        <x-inline-search :placeholder="$searchPlaceholder" :category="$category ?? null" />
                        <x-sort-dropdown />
                    </div>
                </div>
            </div>

            <!-- Top Pagination -->
            <div class="px-6 py-3 surface-panel-alt border-b">
                {{ $results->links() }}
            </div>

            <!-- Results Table (Desktop) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-800 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-3 text-left">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" id="chkSelectAll" x-model="allChecked" @change="toggleAll()">
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
                    <tbody class="surface-panel divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($results as $result)
                            <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700 transition">
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" name="release[]" value="{{ $result->guid }}" @change="onCheckboxChange()">
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex items-start">
                                        @php
                                            $coverUrl = ($result->cover ?? false) ? $result->cover : getReleaseCover($result);
                                            $hasValidCover = $coverUrl && !str_contains($coverUrl, 'no-cover.png');
                                        @endphp
                                        @if($hasValidCover)
                                            <a href="{{ url('/details/' . $result->guid) }}" class="shrink-0 bg-gray-100 dark:bg-gray-700 rounded mr-3" x-show="showThumbs" @unless(request()->query('thumbs') === '1') style="display:none" @endunless>
                                                <img src="{{ request()->query('thumbs') === '1' ? $coverUrl : '' }}" x-bind:src="showThumbs ? '{{ $coverUrl }}' : ''" class="w-12 h-16 object-cover rounded shadow-sm hover:shadow-md transition" alt="Cover" loading="lazy">
                                            </a>
                                        @endif
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="{{ url('/details/' . $result->guid) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium wrap-break-word break-all">{{ $result->searchname }}</a>
                                                @if(!empty($result->report_count) && $result->report_count > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200"
                                                          title="Reported: {{ \App\Models\ReleaseReport::reasonKeysToLabels($result->report_reasons ?? '') }}">
                                                        <i class="fas fa-flag mr-1"></i> Reported ({{ $result->report_count }})
                                                    </span>
                                                @endif
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
                                                            class="mediainfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 hover:bg-primary-200 dark:hover:bg-primary-800 transition cursor-pointer"
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
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ userDateDiffForHumans($result->adddate) }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $result->size_formatted ?? number_format($result->size / 1073741824, 2) . ' GB' }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    @if($result->totalpart > 0)
                                        <button type="button"
                                                class="filelist-badge text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium cursor-pointer hover:underline"
                                                data-guid="{{ $result->guid }}"
                                                title="View file list">
                                            {{ $result->totalpart ?? 0 }}
                                        </button>
                                    @else
                                        {{ $result->totalpart ?? 0 }}
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400"></i> {{ $result->grabs ?? 0 }}</span>
                                        <span title="Comments"><i class="fas fa-comment text-primary-600 dark:text-primary-400"></i> {{ $result->comments ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb px-2 py-1 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZB">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a href="{{ url('/details/' . $result->guid) }}" class="px-2 py-1 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-800 transition text-sm" title="View Details">
                                            <i class="fa fa-info"></i>
                                        </a>
                                        <a href="#" class="add-to-cart px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition text-sm" data-guid="{{ $result->guid }}" title="Add to Cart">
                                            <i class="icon_cart fa fa-shopping-basket"></i>
                                        </a>
                                        @if(!empty($result->imdbid) && $result->imdbid != '0' && $result->imdbid != 0 && $result->imdbid != '0000000')
                                            <a href="{{ url('/mymovies?id=add&imdb=' . $result->imdbid) }}"
                                               class="px-2 py-1 bg-purple-600 dark:bg-purple-700 text-white rounded-lg hover:bg-purple-700 dark:hover:bg-purple-800 transition text-sm"
                                               title="Add to My Movies">
                                                <i class="fa fa-film"></i>
                                            </a>
                                        @endif
                                        <x-report-button :release-id="$result->id" variant="icon" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden space-y-3 px-4 py-4">
                @foreach($results as $result)
                    <div class="surface-panel border rounded-xl p-4 hover:shadow-md transition">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:bg-gray-700 mt-1" name="release[]" value="{{ $result->guid }}" @change="onCheckboxChange()">
                            <div class="flex-1 min-w-0">
                                @php
                                    $mCoverUrl = ($result->cover ?? false) ? $result->cover : getReleaseCover($result);
                                    $mHasCover = $mCoverUrl && !str_contains($mCoverUrl, 'no-cover.png');
                                @endphp
                                @if($mHasCover)
                                    <a href="{{ url('/details/' . $result->guid) }}" class="block mb-2 bg-gray-100 dark:bg-gray-700 rounded-lg" x-show="showThumbs" @unless(request()->query('thumbs') === '1') style="display:none" @endunless>
                                        <img src="{{ request()->query('thumbs') === '1' ? $mCoverUrl : '' }}" x-bind:src="showThumbs ? '{{ $mCoverUrl }}' : ''" class="w-16 h-20 object-cover rounded-lg shadow-sm" alt="Cover" loading="lazy">
                                    </a>
                                @endif
                                <a href="{{ url('/details/' . $result->guid) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium wrap-break-word text-base">
                                    {{ $result->searchname }}
                                </a>
                                <div class="flex flex-wrap items-center gap-2 mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200">
                                        {{ $result->category_name ?? 'Other' }}
                                    </span>
                                    <span><i class="fas fa-clock mr-1"></i>{{ userDateDiffForHumans($result->adddate) }}</span>
                                    <span><i class="fas fa-hdd mr-1"></i>{{ $result->size_formatted ?? number_format($result->size / 1073741824, 2) . ' GB' }}</span>
                                    <span><i class="fas fa-file mr-1"></i>{{ $result->totalpart ?? 0 }} files</span>
                                    <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400 mr-1"></i>{{ $result->grabs ?? 0 }}</span>
                                </div>
                                <div class="mt-3 flex gap-1 flex-wrap">
                                    <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb px-3 py-1.5 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZB">
                                        <i class="fa fa-download"></i>
                                    </a>
                                    <a href="{{ url('/details/' . $result->guid) }}" class="px-3 py-1.5 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-800 transition text-sm" title="View Details">
                                        <i class="fa fa-info"></i>
                                    </a>
                                    <a href="#" class="add-to-cart px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition text-sm" data-guid="{{ $result->guid }}" title="Add to Cart">
                                        <i class="icon_cart fa fa-shopping-basket"></i>
                                    </a>
                                    <x-report-button :release-id="$result->id" variant="icon" />
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Bottom Pagination -->
            <div class="px-6 py-3 surface-panel-alt border-t">
                {{ $results->links() }}
            </div>
        </form>
    @else
        <x-empty-state
            icon="fas fa-search"
            title="No releases found"
            message="Try adjusting your search criteria or browse other categories."
        />
    @endif

    {{-- All modals (preview, mediainfo, filelist, NFO) are included globally via layouts.main --}}
</div>
@endsection

