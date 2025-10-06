@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site->home_link ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ route('series') }}" class="hover:text-blue-600">TV Series</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li class="text-gray-500">{{ $seriestitles ?? '' }}</li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        @if(!empty($nodata))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-800">
                <i class="fa fa-exclamation-circle mr-2"></i><strong>Sorry!</strong> {{ $nodata }}
            </div>
        @else
            <!-- Series Info Card -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-4">
                <div class="px-4 py-3 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                    <h5 class="text-xl font-bold text-gray-800">
                        <i class="fa fa-tv mr-2 text-indigo-600"></i>{{ $seriestitles ?? '' }}
                        @if(!empty($show['publisher']))
                            <span class="text-sm font-normal text-gray-600 ml-2">({{ $show['publisher'] }})</span>
                        @endif
                    </h5>
                </div>
                <div class="p-6">
                    <!-- Series Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        @if(!empty($show['started']))
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                                <div class="text-xs font-semibold text-blue-600 uppercase mb-1">Series Started</div>
                                <div class="text-lg font-bold text-blue-900">
                                    {{ \Carbon\Carbon::parse($show['started'])->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
                            <div class="text-xs font-semibold text-purple-600 uppercase mb-1">Total Seasons Aired</div>
                            <div class="text-lg font-bold text-purple-900">{{ $totalSeasonsAired ?? 0 }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-lg p-4 border border-pink-200">
                            <div class="text-xs font-semibold text-pink-600 uppercase mb-1">Seasons Available</div>
                            <div class="text-lg font-bold text-pink-900">{{ $totalSeasonsAvailable ?? 0 }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg p-4 border border-indigo-200">
                            <div class="text-xs font-semibold text-indigo-600 uppercase mb-1">Total Episodes Aired</div>
                            <div class="text-lg font-bold text-indigo-900">{{ $totalEpisodesAired ?? 0 }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-violet-50 to-violet-100 rounded-lg p-4 border border-violet-200">
                            <div class="text-xs font-semibold text-violet-600 uppercase mb-1">Episodes Available</div>
                            <div class="text-lg font-bold text-violet-900">{{ $episodeCount ?? 0 }}</div>
                        </div>
                        @if(!empty($firstEpisodeAired))
                            <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg p-4 border border-teal-200">
                                <div class="text-xs font-semibold text-teal-600 uppercase mb-1">First Episode Aired</div>
                                <div class="text-sm font-bold text-teal-900">
                                    {{ $firstEpisodeAired->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        @if(!empty($lastEpisodeAired))
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-4 border border-orange-200">
                                <div class="text-xs font-semibold text-orange-600 uppercase mb-1">Last Episode Aired</div>
                                <div class="text-sm font-bold text-orange-900">
                                    {{ $lastEpisodeAired->format('M d, Y') }}
                                </div>
                            </div>
                        @endif
                        @if(!empty($seriescountry))
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
                                <div class="text-xs font-semibold text-green-600 uppercase mb-1">Country</div>
                                <div class="text-lg font-bold text-green-900">{{ strtoupper($seriescountry) }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Summary with inline poster -->
                    @if(!empty($seriessummary))
                        <div class="mb-6">
                            <h6 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="fa fa-align-left mr-2 text-gray-600"></i>Overview
                            </h6>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                                @if(!empty($show['image']) && $show['image'] != 0)
                                    <div class="lg:col-span-1">
                                        <img class="w-full h-auto rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300"
                                             alt="{{ $seriestitles ?? '' }} Poster"
                                             src="{{ url('/covers/tvshows/' . $show['id'] . '.jpg') }}"/>
                                    </div>
                                    <div class="lg:col-span-3">
                                        <p class="text-gray-700 leading-relaxed">{{ $seriessummary }}</p>
                                    </div>
                                @else
                                    <div class="lg:col-span-4">
                                        <p class="text-gray-700 leading-relaxed">{{ $seriessummary }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Quick Actions & Links -->
                    <div class="mb-4">
                        <h6 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fa fa-link mr-2 text-gray-600"></i>Quick Actions & Links
                        </h6>
                        <div class="flex flex-wrap gap-2">
                            <!-- My Shows Controls -->
                            @if(!empty($myshows) && !empty($myshows['id']))
                                <a class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 inline-flex items-center text-sm font-medium transition"
                                           href="{{ url('/myshows?action=edit&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            <i class="fa fa-pencil-alt mr-2"></i>Edit My Shows
                                        </a>
                                        <a class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 inline-flex items-center text-sm font-medium transition"
                                           title="Remove from My Shows"
                                           href="{{ url('/myshows?action=delete&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            <i class="fa fa-minus mr-2"></i>Remove from My Shows
                                        </a>
                                    @else
                                        <a class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 inline-flex items-center text-sm font-medium transition"
                                           title="Add to My Shows"
                                           href="{{ url('/myshows?action=add&id=' . $show['id'] . '&from=' . urlencode(request()->fullUrl())) }}">
                                            <i class="fa fa-plus mr-2"></i>Add to My Shows
                                        </a>
                                    @endif

                                    <a class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 inline-flex items-center text-sm font-medium shadow-sm transition"
                                       href="{{ url('/rss/full-feed?show=' . $show['id'] . (isset($category) && $category != '' ? '&t=' . $category : '') . '&dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}">
                                        <i class="fa fa-rss mr-2"></i> RSS Feed
                                    </a>

                                    @if(!empty($show['tvdb']) && $show['tvdb'] > 0)
                                        <a class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}http://thetvdb.com/?tab=series&id={{ $show['tvdb'] }}"
                                           title="View at TheTVDB">
                                            <i class="fa fa-database mr-2"></i> TheTVDB
                                        </a>
                                    @endif

                                    @if(!empty($show['tvmaze']) && $show['tvmaze'] > 0)
                                        <a class="px-4 py-2 bg-purple-100 text-purple-800 rounded-lg hover:bg-purple-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}http://tvmaze.com/shows/{{ $show['tvmaze'] }}"
                                           title="View at TVMaze">
                                            <i class="fa fa-tv mr-2"></i> TVMaze
                                        </a>
                                    @endif

                                    @if(!empty($show['trakt']) && $show['trakt'] > 0)
                                        <a class="px-4 py-2 bg-red-100 text-red-800 rounded-lg hover:bg-red-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}http://www.trakt.tv/shows/{{ $show['trakt'] }}"
                                           title="View at TraktTv">
                                            <i class="fa fa-heart mr-2"></i> Trakt
                                        </a>
                                    @endif

                                    @if(!empty($show['tvrage']) && $show['tvrage'] > 0)
                                        <a class="px-4 py-2 bg-orange-100 text-orange-800 rounded-lg hover:bg-orange-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}http://www.tvrage.com/shows/id-{{ $show['tvrage'] }}"
                                           title="View at TV Rage">
                                            <i class="fa fa-external-link-alt mr-2"></i> TV Rage
                                        </a>
                                    @endif

                                    @if(!empty($show['imdb']) && $show['imdb'] > 0)
                                        <a class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}https://www.imdb.com/title/tt{{ str_pad($show['imdb'], 7, '0', STR_PAD_LEFT) }}"
                                           title="View at IMDb">
                                            <i class="fa fa-film mr-2"></i> IMDb
                                        </a>
                                    @endif

                                    @if(!empty($show['tmdb']) && $show['tmdb'] > 0)
                                        <a class="px-4 py-2 bg-cyan-100 text-cyan-800 rounded-lg hover:bg-cyan-200 inline-flex items-center text-sm font-medium transition"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}https://www.themoviedb.org/tv/{{ $show['tmdb'] }}"
                                           title="View at TMDb">
                                            <i class="fa fa-video mr-2"></i> TMDb
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Episodes by Season - Tabbed Interface -->
            @if(!empty($seasons))
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                        <h5 class="text-lg font-semibold text-gray-800">
                            <i class="fa fa-list mr-2 text-blue-600"></i>
                            Episodes & Releases
                        </h5>
                    </div>

                    <!-- Season Tabs -->
                    <div class="border-b border-gray-200">
                        <nav class="flex flex-wrap -mb-px px-4" aria-label="Tabs">
                            @foreach($seasons as $seasonNumber => $episodes)
                                <button type="button"
                                        class="season-tab whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors duration-200 {{ $loop->first ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                                        data-season="{{ $seasonNumber }}"
                                        onclick="switchSeason({{ $seasonNumber }})">
                                    Season {{ $seasonNumber }}
                                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs {{ $loop->first ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ count($episodes) }}
                                    </span>
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    <!-- Season Content -->
                    <div class="p-4">
                        @foreach($seasons as $seasonNumber => $episodes)
                            <div class="season-content {{ $loop->first ? '' : 'hidden' }}" data-season="{{ $seasonNumber }}">
                                @foreach($episodes as $episodeNumber => $releases)
                                    <div class="mb-4 pb-4 border-b border-gray-200 last:border-b-0">
                                        <h6 class="font-semibold text-gray-700 mb-2">
                                            Episode {{ $episodeNumber }}
                                        </h6>
                                        <div class="space-y-2">
                                            @foreach($releases as $release)
                                                <div class="flex items-center justify-between bg-gray-50 rounded p-3 hover:bg-gray-100">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <a href="{{ url('/details/' . $release->guid) }}"
                                                               class="text-blue-600 hover:text-blue-800 font-medium">
                                                                {{ $release->searchname }}
                                                            </a>
                                                            @if(!empty($release->failed) && $release->failed > 0)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"
                                                                      title="{{ $release->failed }} user(s) reported download failure">
                                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $release->failed }})
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            <span class="mr-3">
                                                                <i class="fa fa-hdd-o mr-1"></i>{{ formatBytes($release->size) }}
                                                            </span>
                                                            <span>
                                                                <i class="fa fa-clock-o mr-1"></i>{{ \Carbon\Carbon::parse($release->postdate)->diffForHumans() }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <a href="{{ url('/getnzb?id=' . $release->guid) }}"
                                                           class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                                                           title="Download NZB"
                                                           onclick="showToast('Downloading NZB...', 'success')">
                                                            <i class="fa fa-download"></i>
                                                        </a>
                                                        <a href="{{ url('/details/' . $release->guid) }}"
                                                           class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm"
                                                           title="View Details">
                                                            <i class="fa fa-info-circle"></i>
                                                        </a>
                                                        <a href="#" class="add-to-cart px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                                           data-guid="{{ $release->guid }}"
                                                           title="Add to cart">
                                                            <i class="icon_cart fa fa-shopping-basket"></i>
                                                        </a>
                                                    </div>
                                                </div>

<script>
function switchSeason(seasonNumber) {
    // Hide all season content
    document.querySelectorAll('.season-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active styling from all tabs
    document.querySelectorAll('.season-tab').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('border-transparent', 'text-gray-500');

        // Update badge styling
        const badge = tab.querySelector('span');
        if (badge) {
            badge.classList.remove('bg-blue-100', 'text-blue-800');
            badge.classList.add('bg-gray-100', 'text-gray-600');
        }
    });

    // Show selected season content
    const selectedContent = document.querySelector(`.season-content[data-season="${seasonNumber}"]`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
    }

    // Add active styling to selected tab
    const selectedTab = document.querySelector(`.season-tab[data-season="${seasonNumber}"]`);
    if (selectedTab) {
        selectedTab.classList.remove('border-transparent', 'text-gray-500');
        selectedTab.classList.add('border-blue-500', 'text-blue-600');

        // Update badge styling
        const badge = selectedTab.querySelector('span');
        if (badge) {
            badge.classList.remove('bg-gray-100', 'text-gray-600');
            badge.classList.add('bg-blue-100', 'text-blue-800');
        }
    }
}
</script>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
                    <i class="fa fa-info-circle mr-2"></i>
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

