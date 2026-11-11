@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
@php
    $consoleCategory = is_object($catname) ? $catname->title : ($catname ?: 'All consoles');
@endphp
<x-page-header title="Console" :current="$consoleCategory" eyebrow="Games and releases" description="Explore console titles and compare every indexed release in one place." icon="fas fa-gamepad">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-gamepad" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
        <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $consoleCategory }}</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm">
    @php
        $consoleFilterUrl = route('Console', array_filter(['id' => $categorytitle ?? null]));
    @endphp
    <form method="get" action="{{ $consoleFilterUrl }}" class="border-b border-line px-4 py-4 sm:px-6" role="search" aria-label="Filter console titles">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
            <x-field label="Title" for="console-title" name="title">
                <x-input id="console-title" name="title" :value="$title ?? ''" placeholder="Search by title" />
            </x-field>
            <x-field label="Platform" for="console-platform" name="platform">
                <x-input id="console-platform" name="platform" :value="$platform ?? ''" placeholder="Search by platform" />
            </x-field>
            <div class="flex gap-2">
                <x-button type="submit" icon="fa-solid fa-search">Search</x-button>
                <x-button :href="$consoleFilterUrl" variant="secondary" icon="fa-solid fa-xmark">Clear</x-button>
            </div>
        </div>
    </form>

    @if($results->count() > 0)
        <form id="nzb_multi_operations_form" method="get" x-data="releaseMultiOps">
            <div class="space-y-3 px-6 pt-4">
                <x-cover-results-toolbar
                    :results="$results"
                    icon="fa-solid fa-gamepad"
                    :title="$consoleCategory"
                    covgroup="console"
                    :category="$categorytitle ?? 'All'"
                    parentcat="Console"
                    search-placeholder="Search in Console..."
                    :search-category="$category ?? null"
                    total-label="releases"
                    :category-items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, route('Console'), 'All consoles', true)"
                    :ordering="$ordering ?? null"
                />
                <x-release-bulk-actions />
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
                                            <img width="128" height="192" class="w-32 h-48 object-cover"
                                                 src="{{ getReleaseCover($result) }}"
                                                 alt="{{ $result->title }}"
                                                 loading="lazy"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                            @if($totalFailed > 0)
                                                <div class="absolute top-2 right-2">
                                                    <span class="px-2 py-1 bg-danger-600 dark:bg-danger-700 text-white text-xs rounded-full">
                                                        <i aria-hidden="true" class="fa-solid fa-exclamation-circle mr-1"></i>{{ $totalFailed }} Failed
                                                    </span>
                                                </div>
                                            @endif
                                        </a>
                                    @else
                                        <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                            <i aria-hidden="true" class="fas fa-gamepad text-gray-400 text-2xl"></i>
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
                                                    <span><i aria-hidden="true" class="fas fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($result->releasedate)->format('Y') }}</span>
                                                @endif
                                                @if(isset($result->platform) && $result->platform != "")
                                                    <span><i aria-hidden="true" class="fas fa-gamepad mr-1"></i> {{ $result->platform }}</span>
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
                                                    <a target="_blank" rel="noopener noreferrer" href="{{ $site->dereferrerLink }}{{ $result->url }}"
                                                       title="View Game page" class="inline-flex items-center px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700">
                                                       <i aria-hidden="true" class="fa-solid fa-shopping-cart mr-1"></i>Source
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
@endpush
