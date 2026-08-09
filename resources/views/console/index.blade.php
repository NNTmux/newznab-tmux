@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    @php
        $crumbs = [['label' => 'Home', 'url' => url($site['home_link'] ?? '/'), 'icon' => 'fas fa-home']];
        if (!empty($catname) && is_object($catname) && !empty($catname->parent)) {
            $crumbs[] = ['label' => $catname->parent->title, 'url' => url('/browse/' . $catname->parent->title)];
            $crumbs[] = ['label' => $catname->title, 'url' => url('/browse/' . $catname->title)];
        } else {
            $crumbs[] = ['label' => 'Console / ' . (is_object($catname) ? $catname->title : $catname)];
        }
    @endphp
    <x-breadcrumb :items="$crumbs" />

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

                    <!-- Right Section - Inline Search -->
                    <div class="flex items-center">
                        <x-inline-search placeholder="Search in Console..." :category="$category ?? null" />
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
                            $guid = !empty($releases) ? $releases[0]->guid : null;
                            $totalFailed = collect($releases)->sum(fn($r) => (int)($r->failed_count ?? 0));
                        @endphp

                        <div class="surface-panel border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                            <div class="flex flex-row">
                                <!-- Console Cover -->
                                <div class="shrink-0">
                                    @if($guid)
                                        <a href="{{ url('/details/' . $guid) }}" class="block relative">
                                            <img class="w-32 h-48 object-cover"
                                                 src="{{ getReleaseCover($result) }}"
                                                 alt="{{ $result->title }}"
                                                 loading="lazy"
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
                                                       <i class="fa fa-shopping-cart mr-1"></i>Source
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <x-cover-release-list
                                        :releases="$releases"
                                        :total-releases="$totalReleases"
                                        :show-checkbox="true"
                                        :show-add-date="true"
                                        :show-group="true"
                                        :show-stats="true"
                                    />
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
        <x-empty-state
            icon="fas fa-gamepad"
            title="No console releases found"
            message="No console releases with covers available! Check back later."
        />
    @endif
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection

@push('scripts')
@include('partials.cart-script')
@endpush
