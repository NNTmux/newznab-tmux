@extends('layouts.main')

@section('content')
@php
    $returnTo = urlencode(request()->fullUrl());
    $hasPoster = !empty($show['image']) && $show['image'] != 0;
    $network = $show['publisher'] ?? null;
    $externalLinks = array_filter([
        'TheTVDB' => !empty($show['tvdb']) && $show['tvdb'] > 0 ? 'https://thetvdb.com/?tab=series&id='.$show['tvdb'] : null,
        'TVMaze' => !empty($show['tvmaze']) && $show['tvmaze'] > 0 ? 'https://www.tvmaze.com/shows/'.$show['tvmaze'] : null,
        'Trakt' => !empty($show['trakt']) && $show['trakt'] > 0 ? 'https://trakt.tv/shows/'.$show['trakt'] : null,
        'IMDb' => !empty($show['imdb']) && $show['imdb'] > 0 ? 'https://www.imdb.com/title/tt'.$show['imdb'] : null,
        'TMDb' => !empty($show['tmdb']) && $show['tmdb'] > 0 ? 'https://www.themoviedb.org/tv/'.$show['tmdb'] : null,
    ]);
    $facts = array_filter([
        'Network' => $network ?: null,
        'Country' => !empty($seriescountry) ? strtoupper($seriescountry) : null,
        'Started' => !empty($show['started']) ? \Carbon\Carbon::parse($show['started'])->format('M d, Y') : null,
        'Seasons' => empty($nodata) ? number_format($totalSeasonsAvailable ?? 0).' available · '.number_format($totalSeasonsAired ?? 0).' aired' : null,
        'Episodes' => empty($nodata) ? number_format($episodeCount ?? 0).' available · '.number_format($totalEpisodesAired ?? 0).' aired' : null,
        'First aired' => !empty($firstEpisodeAired) ? $firstEpisodeAired->format('M d, Y') : null,
        'Last aired' => !empty($lastEpisodeAired) ? $lastEpisodeAired->format('M d, Y') : null,
    ]);
@endphp
<x-page-header :title="!empty($seriestitles) ? $seriestitles : 'TV series'" eyebrow="Series details" description="Explore available seasons, episodes, and release information." icon="fas fa-tv">
    <x-slot:stats>
        @if($network)
            <span class="workspace-hero__stat"><i class="fas fa-tower-broadcast" aria-hidden="true"></i>{{ $network }}</span>
        @endif
        <span class="workspace-hero__stat"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($totalSeasonsAvailable ?? 0) }} {{ Str::plural('season', (int) ($totalSeasonsAvailable ?? 0)) }}</span>
        <span class="workspace-hero__stat"><i class="fas fa-clapperboard" aria-hidden="true"></i>{{ number_format($episodeCount ?? 0) }} {{ Str::plural('episode', (int) ($episodeCount ?? 0)) }}</span>
    </x-slot:stats>
    <x-slot:actions>
        <a href="{{ route('series') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to TV shows</a>
    </x-slot:actions>
</x-page-header>

<div class="series-detail-page space-y-6">
        @if(!empty($nodata))
            <x-alert tone="warning" title="Sorry!">{{ $nodata }}</x-alert>
        @else
            <section class="series-main-card surface-panel rounded-xl p-5 sm:p-6" aria-label="Series information">
                <div @class([
                    'grid grid-cols-1 gap-6',
                    'sm:grid-cols-[10rem_minmax(0,1fr)] lg:grid-cols-[12rem_minmax(0,1fr)]' => $hasPoster,
                ])>
                    @if($hasPoster)
                        <div>
                            <img width="320" height="480" class="series-detail-poster mx-auto aspect-[2/3] w-full max-w-48 object-cover sm:max-w-none"
                                 alt="{{ $seriestitles ?? '' }} poster"
                                 loading="lazy" decoding="async"
                                 src="{{ getImageAssetUrl('tvshows', (string) $show['id'], url('/covers/tvshows/no-cover.jpg')) }}"/>
                        </div>
                    @endif

                    <div class="min-w-0 space-y-5">
                        @if($facts !== [])
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3 xl:grid-cols-4">
                                @foreach($facts as $label => $value)
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-fg-muted">{{ $label }}</dt>
                                        <dd class="mt-0.5 truncate font-medium text-fg" title="{{ $value }}">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if(!empty($seriessummary))
                            <div>
                                <h2 class="mb-1.5 text-sm font-semibold text-fg">Overview</h2>
                                <p class="leading-relaxed text-fg-muted">{{ $seriessummary }}</p>
                            </div>
                        @endif

                        {{-- Watchlist, feed and external links --}}
                        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                            @if(!empty($myshows) && !empty($myshows['id']))
                                <x-badge tone="success" icon="fa-solid fa-check" size="md">Following</x-badge>
                                <x-button size="sm" variant="secondary" icon="fa-solid fa-pen" :href="url('/myshows?action=edit&id='.$show['id'].'&from='.$returnTo)">Edit</x-button>
                                <form method="POST" action="{{ url('/myshows?action=delete&id='.$show['id'].'&from='.$returnTo) }}"
                                      x-data="confirmForm" @submit.prevent="submit()" data-title="Remove show" data-message="Stop following this show?" data-confirm-text="Remove" data-type="danger">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="ghost" icon="fa-solid fa-minus" class="text-danger-600 dark:text-danger-400">Remove</x-button>
                                </form>
                            @else
                                <x-button size="sm" icon="fa-solid fa-plus" :href="url('/myshows?action=add&id='.$show['id'].'&from='.$returnTo)">Add to My shows</x-button>
                            @endif

                            <x-button size="sm" variant="secondary" icon="fa-solid fa-rss"
                                      :href="url('/rss/full-feed?show='.$show['id'].(isset($category) && $category != '' ? '&t='.$category : '').'&dl=1&i='.auth()->id().'&api_token='.auth()->user()->api_token)">RSS feed</x-button>

                            @if($externalLinks !== [])
                                <span class="mx-1 hidden h-5 w-px bg-gray-300 sm:inline-block dark:bg-gray-600" aria-hidden="true"></span>
                                @foreach($externalLinks as $provider => $link)
                                    <a href="{{ $site->dereferrerLink }}{{ $link }}" target="_blank" rel="noopener noreferrer" title="View on {{ $provider }}"
                                       class="inline-flex min-h-9 items-center gap-1.5 rounded-control border border-line px-3 text-xs font-medium text-fg-muted transition hover:bg-surface-hover hover:text-fg">
                                        {{ $provider }}<i aria-hidden="true" class="fa-solid fa-arrow-up-right-from-square text-[0.65rem]"></i>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <!-- Episodes by Season - Tabbed Interface -->
            @if(!empty($seasonTabs))
                <div id="series-episodes" x-data="seriesSeasonLoader" data-series-season-loader>
                <form id="nzb_multi_operations_form" method="get" x-data="releaseMultiOps">
                <div class="series-episodes-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                                <i aria-hidden="true" class="fa-solid fa-list mr-2 text-link dark:text-primary-400"></i>
                                Episodes & Releases
                            </h3>
                            <x-release-bulk-actions />
                        </div>
                    </div>

                    <!-- Season Tabs -->
                    <div class="border-b border-gray-200">
                        <div class="flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700 mr-2" id="chkSelectAll" x-model="allChecked" @change="toggleAll()">
                            <label for="chkSelectAll" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Select All</label>
                        </div>
                        <nav class="flex flex-wrap -mb-px px-4" aria-label="Tabs">
                            @foreach($seasonTabs as $tab)
                                <a href="{{ $tab['url'] }}"
                                        class="series-season-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors duration-200 {{ $tab['active'] ? 'border-primary-500 text-link' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-300 hover:border-gray-300' }}"
                                        data-season="{{ $tab['season'] }}"
                                        data-series-season-link
                                        @if($tab['active']) aria-current="page" @endif>
                                    Season {{ $tab['season'] }}
                                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs {{ $tab['active'] ? 'bg-primary-100 text-primary-800 dark:text-primary-200' : 'bg-gray-100 dark:bg-gray-800 text-gray-600' }}">
                                        {{ $tab['count'] }}
                                    </span>
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    <!-- Season Content -->
                    @include('series.partials.season-content')
                </div>
                </form>
                <div data-series-pagination-container>
                    @include('series.partials.season-pagination')
                </div>
                </div>
            @else
                <x-alert tone="info">No episodes or releases found for this series.</x-alert>
            @endif
        @endif
</div>
@endsection

@push('scripts')
@endpush
