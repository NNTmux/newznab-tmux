@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site['home_link'] ?? '/') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                @if(!empty($catname) && is_object($catname) && !empty($catname->parent))
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="{{ url('/browse/' . $catname->parent->title) }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">{{ $catname->parent->title }}</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="{{ url('/browse/' . $catname->title) }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">{{ $catname->title }}</a>
                        </div>
                    </li>
                @else
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-500">Console / {{ is_object($catname) ? $catname->title : $catname }}</span>
                        </div>
                    </li>
                @endif
            </ol>
        </nav>
    </div>

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get" x-data="releaseMultiOps">
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <!-- Left Section -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <x-view-toggle
                            current-view="covers"
                            covgroup="console"
                            :category="$categorytitle ?? 'All'"
                            parentcat="Console"
                            :shows="false"
                        />

                        <div class="flex items-center gap-2">
                            <small class="text-gray-600 dark:text-gray-400">With Selected:</small>
                            <div class="flex gap-1">
                                <button type="button" class="nzb_multi_operations_download px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded hover:bg-green-700 dark:hover:bg-green-800 transition text-sm" title="Download NZBs">
                                    <i class="fa fa-cloud-download"></i>
                                </button>
                                <button type="button" class="nzb_multi_operations_cart px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition text-sm" title="Send to Download Basket">
                                    <i class="fa fa-shopping-basket"></i>
                                </button>
                                @if(isset($isadmin) && $isadmin)
                                    <button type="button" class="nzb_multi_operations_edit px-3 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition text-sm">Edit</button>
                                    <button type="button" class="nzb_multi_operations_delete px-3 py-1 bg-red-600 dark:bg-red-700 text-white rounded hover:bg-red-700 dark:hover:bg-red-800 transition text-sm">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Results Grid -->
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($resultsadd as $result)
                        @php
                            $releases = $result->releases ?? [];
                            $totalReleases = $result->total_releases ?? count($releases);
                            $maxReleases = 2;
                            $displayReleases = array_slice($releases, 0, $maxReleases);
                            $guid = !empty($displayReleases) ? $displayReleases[0]->guid : null;
                            $totalFailed = collect($releases)->sum(fn($r) => (int)($r->failed_count ?? 0));
                        @endphp

                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                            <div class="flex flex-row">
                                <!-- Console Cover -->
                                <div class="shrink-0">
                                    @if($guid)
                                        <a href="{{ url('/details/' . $guid) }}" class="block relative">
                                            <img class="w-32 h-48 object-cover"
                                                 src="{{ getReleaseCover($result) }}"
                                                 alt="{{ $result->title }}"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                            @if($totalFailed > 0)
                                                <div class="absolute top-2 right-2">
                                                    <span class="px-2 py-1 bg-red-600 dark:bg-red-700 text-white text-xs rounded-full">
                                                        <i class="fa fa-exclamation-circle mr-1"></i>{{ $totalFailed }} Failed
                                                    </span>
                                                </div>
                                            @endif
                                        </a>
                                    @else
                                        <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                            <i class="fas fa-gamepad text-gray-400 text-2xl"></i>
                                        </div>
                                    @endif
                                </div>

                                <!-- Console Details -->
                                <div class="flex-1 p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $result->title }}</h3>

                                            <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                @if(isset($result->releasedate) && $result->releasedate != "")
                                                    <span><i class="fas fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($result->releasedate)->format('Y') }}</span>
                                                @endif
                                                @if(isset($result->platform) && $result->platform != "")
                                                    <span><i class="fas fa-gamepad mr-1"></i> {{ $result->platform }}</span>
                                                @endif
                                            </div>

                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                                @if(isset($result->genre) && $result->genre != "")
                                                    <div class="flex flex-wrap gap-1">
                                                        <strong>Genre:</strong>
                                                        @foreach(explode(', ', $result->genre) as $genre)
                                                            <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded text-xs">{{ $genre }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if(isset($result->publisher) && $result->publisher != "")
                                                    <div><strong>Publisher:</strong> {{ $result->publisher }}</div>
                                                @endif
                                                @if(isset($result->esrb) && $result->esrb != "")
                                                    <div><strong>Rating:</strong> {{ $result->esrb }}</div>
                                                @endif
                                            </div>

                                            <!-- External Links -->
                                            @if(!empty($result->url))
                                                <div class="mt-2">
                                                    <a target="_blank" href="{{ $site['dereferrer_link'] }}{{ $result->url }}"
                                                       title="View Game page" class="inline-flex items-center px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">
                                                       <i class="fa fa-shopping-cart mr-1"></i>Amazon
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Release Information -->
                                    @if(!empty($displayReleases))
                                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                Available Releases
                                                @if($totalReleases > $maxReleases)
                                                    <span class="text-xs font-normal text-gray-500">(Showing {{ $maxReleases }} of {{ $totalReleases }})</span>
                                                @endif
                                            </h4>
                                            <div class="space-y-2">
                                                @foreach($displayReleases as $release)
                                                    @if($release->searchname)
                                                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-2 border border-gray-200 dark:border-gray-700">
                                                            <div class="space-y-2">
                                                                <div class="flex items-start justify-between gap-2">
                                                                    <!-- Release Name -->
                                                                    <a href="{{ url('/details/' . $release->guid) }}" class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium block break-all flex-1" title="{{ $release->searchname }}">
                                                                        {{ $release->searchname }}
                                                                    </a>
                                                                    <label class="inline-flex items-center shrink-0">
                                                                        <input type="checkbox" class="chkRelease form-checkbox h-4 w-4 text-blue-600" value="{{ $release->guid }}" name="release[]" @change="onCheckboxChange()"/>
                                                                    </label>
                                                                </div>

                                                                <!-- Info Badges -->
                                                                <div class="flex flex-wrap items-center gap-1.5">
                                                                    @if(isset($release->size))
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                                            <i class="fas fa-hdd mr-1"></i>{{ number_format($release->size / 1073741824, 2) }} GB
                                                                        </span>
                                                                    @endif
                                                                    @if(isset($release->postdate))
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                                            <i class="fas fa-calendar-alt mr-1"></i>{{ date('M d, Y H:i', strtotime($release->postdate)) }}
                                                                        </span>
                                                                    @endif
                                                                    @if(isset($release->adddate))
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                                            <i class="fas fa-plus-circle mr-1"></i>{{ userDateDiffForHumans($release->adddate) }}
                                                                        </span>
                                                                    @endif
                                                                    @if(isset($release->nfoid) && !empty($release->nfoid))
                                                                        <button type="button"
                                                                                class="nfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800 transition cursor-pointer"
                                                                                data-guid="{{ $release->guid }}"
                                                                                title="View NFO file">
                                                                            <i class="fas fa-file-alt mr-1"></i> NFO
                                                                        </button>
                                                                    @endif
                                                                    @if(isset($release->group_name) && !empty($release->group_name))
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200" title="Poster/Uploader">
                                                                            <i class="fas fa-user mr-1"></i> {{ $release->group_name }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                <!-- Action Buttons -->
                                                                <div class="flex flex-wrap items-center gap-1.5">
                                                                    <a href="{{ url('/getnzb/' . $release->guid) }}" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-600 dark:bg-green-700 text-white hover:bg-green-700 dark:hover:bg-green-800 transition">
                                                                        <i class="fas fa-download mr-1"></i> Download
                                                                        @if(isset($release->grabs) && $release->grabs > 0)
                                                                            <span class="ml-1 px-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded">{{ $release->grabs }}</span>
                                                                        @endif
                                                                    </a>
                                                                    <button class="add-to-cart inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800 transition" data-guid="{{ $release->guid }}">
                                                                        <i class="fas fa-shopping-cart mr-1"></i> Cart
                                                                    </button>
                                                                    <a href="{{ url('/details/' . $release->guid) }}" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 dark:bg-gray-700 text-white hover:bg-gray-700 dark:hover:bg-gray-800 transition">
                                                                        <i class="fas fa-info-circle mr-1"></i> Details
                                                                        @if(isset($release->comments) && $release->comments > 0)
                                                                            <span class="ml-1 px-1 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs rounded">{{ $release->comments }}</span>
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                {{ $results->links() }}
            </div>
        </form>
    @else
        <div class="px-6 py-8">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-blue-800 dark:text-blue-200">
                <i class="fa fa-info-circle mr-2"></i>
                No console releases with covers available!
            </div>
        </div>
    @endif
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection

@push('scripts')
@include('partials.cart-script')
@endpush
