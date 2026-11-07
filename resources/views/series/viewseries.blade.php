@extends('layouts.main')

@section('content')
<x-page-header :title="!empty($seriestitles) ? $seriestitles : 'TV series'" eyebrow="Series details" description="Explore available seasons, episodes, and release information." icon="fas fa-tv">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($totalSeasonsAvailable ?? 0) }} seasons available</span>
        <span class="workspace-hero__stat"><i class="fas fa-clapperboard" aria-hidden="true"></i>{{ number_format($episodeCount ?? 0) }} episodes available</span>
    </x-slot:stats>
    <x-slot:actions>
        <a href="{{ route('series') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to TV shows</a>
    </x-slot:actions>
</x-page-header>

<div class="series-detail-page surface-panel rounded-xl shadow-sm">
    <div class="px-6 py-4">
        @if(!empty($nodata))
            <div class="bg-warning-50 dark:bg-warning-950/40 border border-warning-200 rounded-lg p-4 text-warning-800 dark:text-warning-200">
                <i aria-hidden="true" class="fa-solid fa-exclamation-circle mr-2"></i><strong>Sorry!</strong> {{ $nodata }}
            </div>
        @else
            <!-- Series Info Card -->
            <div class="series-main-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm mb-4">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <h5 class="text-xl font-bold text-gray-800 dark:text-white">
                        <i aria-hidden="true" class="fa-solid fa-tv mr-2 text-indigo-600 dark:text-indigo-400"></i>{{ $seriestitles ?? '' }}
                        @if(!empty($show['publisher']))
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-300 ml-2">({{ $show['publisher'] }})</span>
                        @endif
                    </h5>
                </div>
                <div class="p-6">
                    <!-- Series Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        @if(!empty($show['started']))
                            <div class="series-stat-card bg-linear-to-br from-primary-50 to-primary-100 rounded-lg p-4 border border-primary-200">
                                <div class="text-xs font-semibold text-link dark:text-primary-400 uppercase mb-1">Series Started</div>
                                <div class="text-lg font-bold text-primary-900 dark:text-primary-200">
                                    {{ \Carbon\Carbon::parse($show['started'])->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        <div class="series-stat-card bg-linear-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
                            <div class="text-xs font-semibold text-purple-600 uppercase mb-1">Total Seasons Aired</div>
                            <div class="text-lg font-bold text-purple-900 dark:text-purple-200">{{ $totalSeasonsAired ?? 0 }}</div>
                        </div>
                        <div class="series-stat-card bg-linear-to-br from-pink-50 to-pink-100 rounded-lg p-4 border border-pink-200">
                            <div class="text-xs font-semibold text-pink-600 uppercase mb-1">Seasons Available</div>
                            <div class="text-lg font-bold text-pink-900 dark:text-pink-200">{{ $totalSeasonsAvailable ?? 0 }}</div>
                        </div>
                        <div class="series-stat-card bg-linear-to-br from-indigo-50 to-indigo-100 rounded-lg p-4 border border-indigo-200">
                            <div class="text-xs font-semibold text-indigo-600 uppercase mb-1">Total Episodes Aired</div>
                            <div class="text-lg font-bold text-indigo-900 dark:text-indigo-200">{{ $totalEpisodesAired ?? 0 }}</div>
                        </div>
                        <div class="series-stat-card bg-linear-to-br from-violet-50 to-violet-100 rounded-lg p-4 border border-violet-200">
                            <div class="text-xs font-semibold text-violet-600 uppercase mb-1">Episodes Available</div>
                            <div class="text-lg font-bold text-violet-900 dark:text-violet-200">{{ $episodeCount ?? 0 }}</div>
                        </div>
                        @if(!empty($firstEpisodeAired))
                            <div class="series-stat-card bg-linear-to-br from-teal-50 to-teal-100 rounded-lg p-4 border border-teal-200">
                                <div class="text-xs font-semibold text-teal-600 uppercase mb-1">First Episode Aired</div>
                                <div class="text-sm font-bold text-teal-900 dark:text-teal-200">
                                    {{ $firstEpisodeAired->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        @if(!empty($lastEpisodeAired))
                            <div class="series-stat-card bg-linear-to-br from-orange-50 to-orange-100 rounded-lg p-4 border border-orange-200">
                                <div class="text-xs font-semibold text-orange-600 uppercase mb-1">Last Episode Aired</div>
                                <div class="text-sm font-bold text-orange-900 dark:text-orange-200">
                                    {{ $lastEpisodeAired->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        @if(!empty($seriescountry))
                            <div class="series-stat-card bg-linear-to-br from-success-50 to-success-100 rounded-lg p-4 border border-success-200">
                                <div class="text-xs font-semibold text-success-600 uppercase mb-1">Country</div>
                                <div class="text-lg font-bold text-success-900 dark:text-success-200">{{ strtoupper($seriescountry) }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Summary with inline poster -->
                    @if(!empty($seriessummary))
                        <div class="mb-6">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <i aria-hidden="true" class="fa-solid fa-align-left mr-2 text-gray-600"></i>Overview
                            </h6>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                                @if(!empty($show['image']) && $show['image'] != 0)
                                    <div class="lg:col-span-1">
                                        <img width="320" height="480" class="series-detail-poster w-full h-auto rounded-lg aspect-[2/3] object-cover"
                                             alt="{{ $seriestitles ?? '' }} Poster"
                                             loading="lazy"
                                             src="{{ getImageAssetUrl('tvshows', (string) $show['id'], url('/covers/tvshows/no-cover.jpg')) }}"/>
                                    </div>
                                    <div class="lg:col-span-3">
                                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $seriessummary }}</p>
                                    </div>
                                @else
                                    <div class="lg:col-span-4">
                                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $seriessummary }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Quick Actions & Links -->
                    <div class="mb-4">
                        <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                            <i aria-hidden="true" class="fa-solid fa-link mr-2 text-gray-600"></i>Quick Actions & Links
                        </h6>
                        <div class="flex flex-wrap gap-2">
                            <!-- My Shows Controls -->
                            @if(!empty($myshows) && !empty($myshows['id']))
                                <a class="px-4 py-2 bg-warning-500 text-white rounded-lg hover:bg-warning-600 inline-flex items-center text-sm font-medium transition"
                                           href="{{ url('/myshows?action=edit&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            <i aria-hidden="true" class="fa-solid fa-pencil-alt mr-2"></i>Edit My Shows
                                        </a>
                                        <form method="POST" action="{{ url('/myshows?action=delete&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-800 inline-flex items-center text-sm font-medium transition"
                                                title="Remove from My Shows">
                                                <i aria-hidden="true" class="fa-solid fa-minus mr-2"></i>Remove from My Shows
                                            </button>
                                        </form>
                                    @else
                                        <a class="px-4 py-2 bg-success-600 dark:bg-success-700 text-white rounded-lg hover:bg-success-700 dark:hover:bg-success-800 inline-flex items-center text-sm font-medium transition"
                                           title="Add to My Shows"
                                           href="{{ url('/myshows?action=add&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            <i aria-hidden="true" class="fa-solid fa-plus mr-2"></i>Add to My Shows
                                        </a>
                                    @endif

                                    <a class="px-4 py-2 bg-linear-to-r from-success-600 to-success-700 text-white rounded-lg hover:from-success-700 hover:to-success-800 inline-flex items-center text-sm font-medium shadow-sm transition"
                                       href="{{ url('/rss/full-feed?show=' . $show['id'] . (isset($category) && $category != '' ? '&t=' . $category : '') . '&dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}">
                                        <i aria-hidden="true" class="fa-solid fa-rss mr-2"></i> RSS Feed
                                    </a>

                                    @if(!empty($show['tvdb']) && $show['tvdb'] > 0)
                                        <a class="px-4 py-2 bg-primary-100 dark:bg-primary-900/40 text-primary-800 dark:text-primary-200 rounded-lg hover:bg-primary-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}http://thetvdb.com/?tab=series&id={{ $show['tvdb'] }}"
                                           title="View at TheTVDB">
                                            <i aria-hidden="true" class="fa-solid fa-database mr-2"></i> TheTVDB
                                        </a>
                                    @endif

                                    @if(!empty($show['tvmaze']) && $show['tvmaze'] > 0)
                                        <a class="px-4 py-2 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200 rounded-lg hover:bg-purple-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}http://tvmaze.com/shows/{{ $show['tvmaze'] }}"
                                           title="View at TVMaze">
                                            <i aria-hidden="true" class="fa-solid fa-tv mr-2"></i> TVMaze
                                        </a>
                                    @endif

                                    @if(!empty($show['trakt']) && $show['trakt'] > 0)
                                        <a class="px-4 py-2 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 rounded-lg hover:bg-red-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}http://www.trakt.tv/shows/{{ $show['trakt'] }}"
                                           title="View at TraktTv">
                                            <i aria-hidden="true" class="fa-solid fa-heart mr-2"></i> Trakt
                                        </a>
                                    @endif

                                    @if(!empty($show['tvrage']) && $show['tvrage'] > 0)
                                        <a class="px-4 py-2 bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-200 rounded-lg hover:bg-orange-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}http://www.tvrage.com/shows/id-{{ $show['tvrage'] }}"
                                           title="View at TV Rage">
                                            <i aria-hidden="true" class="fa-solid fa-external-link-alt mr-2"></i> TV Rage
                                        </a>
                                    @endif

                                    @if(!empty($show['imdb']) && $show['imdb'] > 0)
                                        <a class="px-4 py-2 bg-warning-100 dark:bg-warning-900/40 text-warning-800 dark:text-warning-200 rounded-lg hover:bg-warning-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $show['imdb'] }}"
                                           title="View at IMDb">
                                            <i aria-hidden="true" class="fa-solid fa-film mr-2"></i> IMDb
                                        </a>
                                    @endif

                                    @if(!empty($show['tmdb']) && $show['tmdb'] > 0)
                                        <a class="px-4 py-2 bg-cyan-100 dark:bg-cyan-900/40 text-cyan-800 dark:text-cyan-200 rounded-lg hover:bg-cyan-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrerLink }}https://www.themoviedb.org/tv/{{ $show['tmdb'] }}"
                                           title="View at TMDb">
                                            <i aria-hidden="true" class="fa-solid fa-video mr-2"></i> TMDb
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Episodes by Season - Tabbed Interface -->
            @if(!empty($seasonTabs))
                <div id="series-episodes" x-data="seriesSeasonLoader" data-series-season-loader>
                <form id="nzb_multi_operations_form" method="get" x-data="releaseMultiOps">
                <div class="series-episodes-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                                <i aria-hidden="true" class="fa-solid fa-list mr-2 text-link dark:text-primary-400"></i>
                                Episodes & Releases
                            </h5>
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
                <div class="bg-primary-50 dark:bg-primary-950/40 border border-primary-200 rounded-lg p-4 text-primary-800 dark:text-primary-200">
                    <i aria-hidden="true" class="fa-solid fa-info-circle mr-2"></i>
                    No episodes/releases found for this series.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('partials.cart-script')
@endpush
