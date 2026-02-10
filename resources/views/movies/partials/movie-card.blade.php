{{-- Movie Card Partial --}}
{{-- Props: $result (movie object), $layout (1 or 2), $site (site configuration) --}}

@props([
    'result',
    'layout' => 2,
    'site' => [],
])

@php
    // Get the first GUID from the comma-separated list
    $guid = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid)[0] : null;

    // Parse release data
    $releaseNames = isset($result->grp_release_name) ? explode('#', $result->grp_release_name) : [];
    $releaseSizes = isset($result->grp_release_size) ? explode(',', $result->grp_release_size) : [];
    $releaseGuids = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid) : [];
    $releaseIds = isset($result->grp_release_id) ? explode(',', $result->grp_release_id) : [];
    $releasePostDates = isset($result->grp_release_postdate) ? explode(',', $result->grp_release_postdate) : [];
    $releaseAddDates = isset($result->grp_release_adddate) ? explode(',', $result->grp_release_adddate) : [];
    $releaseHasPreview = isset($result->grp_haspreview) ? explode(',', $result->grp_haspreview) : [];
    $releaseJpgStatus = isset($result->grp_jpgstatus) ? explode(',', $result->grp_jpgstatus) : [];
    $releaseNfoStatus = isset($result->grp_nfostatus) ? explode(',', $result->grp_nfostatus) : [];
    $releaseFromNames = isset($result->grp_release_fromname) ? explode(',', $result->grp_release_fromname) : [];

    // Limit to maximum 2 releases
    $maxReleases = 2;
    $totalReleases = count($releaseNames);
    $releaseNames = array_slice($releaseNames, 0, $maxReleases);
    $releaseSizes = array_slice($releaseSizes, 0, $maxReleases);
    $releaseGuids = array_slice($releaseGuids, 0, $maxReleases);
    $releaseIds = array_slice($releaseIds, 0, $maxReleases);
    $releasePostDates = array_slice($releasePostDates, 0, $maxReleases);
    $releaseAddDates = array_slice($releaseAddDates, 0, $maxReleases);
    $releaseHasPreview = array_slice($releaseHasPreview, 0, $maxReleases);
    $releaseJpgStatus = array_slice($releaseJpgStatus, 0, $maxReleases);
    $releaseNfoStatus = array_slice($releaseNfoStatus, 0, $maxReleases);
    $releaseFromNames = array_slice($releaseFromNames, 0, $maxReleases);

    // Image dimensions based on layout
    $coverClass = $layout == 1 ? 'w-48 h-72' : 'w-32 h-48';
@endphp

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:shadow-xl transition-shadow duration-300">
    <div class="flex flex-row">
        {{-- Movie Poster --}}
        <div class="shrink-0">
            @if($guid)
                <a href="{{ url('/details/' . $guid) }}" class="block">
                    @if(isset($result->cover) && $result->cover)
                        <img src="{{ $result->cover }}"
                             alt="{{ $result->title }}"
                             class="{{ $coverClass }} object-cover movie-cover"
                             loading="lazy">
                    @else
                        <div class="{{ $coverClass }} bg-gray-200 dark:bg-gray-700 flex items-center justify-center movie-cover">
                            <i class="fas fa-film text-gray-400 text-3xl"></i>
                        </div>
                    @endif
                </a>
            @else
                @if(isset($result->cover) && $result->cover)
                    <img src="{{ $result->cover }}"
                         alt="{{ $result->title }}"
                         class="{{ $coverClass }} object-cover movie-cover"
                         loading="lazy">
                @else
                    <div class="{{ $coverClass }} bg-gray-200 dark:bg-gray-700 flex items-center justify-center movie-cover">
                        <i class="fas fa-film text-gray-400 text-3xl"></i>
                    </div>
                @endif
            @endif
        </div>

        {{-- Movie Details --}}
        <div class="flex-1 p-4 min-w-0">
            {{-- Title and Report Badges --}}
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1 min-w-0">
                    @if(isset($result->imdbid) && $result->imdbid)
                        <a href="{{ route('movie.view', ['imdbid' => $result->imdbid]) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ $result->title }}</h3>
                        </a>
                    @else
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ $result->title }}</h3>
                    @endif

                    {{-- Report Badges --}}
                    <div class="flex flex-wrap gap-2 mt-1">
                        @if(!empty($result->grp_release_reports))
                            @php
                                $reportCounts = array_filter(explode(',', $result->grp_release_reports));
                                $totalReports = array_sum($reportCounts);
                                $reportReasonsRaw = !empty($result->grp_release_report_reasons)
                                    ? implode(',', array_unique(array_filter(preg_split('/[|,]/', $result->grp_release_report_reasons))))
                                    : '';
                                $reportReasons = \App\Models\ReleaseReport::reasonKeysToLabels($reportReasonsRaw);
                            @endphp
                            @if($totalReports > 0)
                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 border border-orange-200 dark:border-orange-800"
                                     title="Reported: {{ $reportReasons }}">
                                    <i class="fas fa-flag mr-1"></i>
                                    <span>{{ $totalReports }} report{{ $totalReports > 1 ? 's' : '' }}</span>
                                </div>
                            @endif
                        @endif

                        @if(!empty($result->grp_release_failed))
                            @php
                                $failedCounts = array_filter(explode(',', $result->grp_release_failed));
                                $totalFailed = array_sum($failedCounts);
                            @endphp
                            @if($totalFailed > 0)
                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Year and Rating --}}
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
                        @if(isset($result->year) && $result->year)
                            <span><i class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                        @endif
                        @if(isset($result->rating) && $result->rating)
                            <span class="text-yellow-600 dark:text-yellow-400">
                                <i class="fas fa-star mr-1"></i> {{ $result->rating }}
                            </span>
                        @endif
                    </div>

                    {{-- External Links --}}
                    <div class="flex items-center gap-2 mt-2 text-xs">
                        @if(isset($result->imdbid) && $result->imdbid)
                            <a href="{{ ($site['dereferrer_link'] ?? '') }}https://www.imdb.com/title/tt{{ $result->imdbid }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800 transition">
                                <i class="fab fa-imdb mr-1"></i> IMDb
                            </a>
                        @endif
                        @if(isset($result->tmdbid) && $result->tmdbid)
                            <a href="{{ ($site['dereferrer_link'] ?? '') }}https://www.themoviedb.org/movie/{{ $result->tmdbid }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                                <i class="fas fa-film mr-1"></i> TMDb
                            </a>
                        @endif
                        @if(isset($result->traktid) && $result->traktid)
                            <a href="{{ ($site['dereferrer_link'] ?? '') }}https://trakt.tv/movies/{{ $result->traktid }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded hover:bg-red-200 dark:hover:bg-red-800 transition">
                                <i class="fas fa-heart mr-1"></i> Trakt
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Plot --}}
            @if(isset($result->plot) && $result->plot)
                <p class="text-gray-700 dark:text-gray-300 text-sm mt-3 line-clamp-3">{{ $result->plot }}</p>
            @endif

            {{-- Metadata: Genre, Director, Actors --}}
            <div class="mt-3 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                @if(isset($result->genre) && $result->genre)
                    <div>
                        <strong class="text-gray-700 dark:text-gray-300">Genre:</strong> {!! $result->genre !!}
                    </div>
                @endif
                @if(isset($result->director) && $result->director)
                    <div>
                        <strong class="text-gray-700 dark:text-gray-300">Director:</strong> {!! $result->director !!}
                    </div>
                @endif
                @if(isset($result->actors) && $result->actors)
                    <div>
                        <strong class="text-gray-700 dark:text-gray-300">Actors:</strong> {!! $result->actors !!}
                    </div>
                @endif
            </div>

            {{-- Release Information --}}
            @if($guid && !empty($releaseNames[0]))
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        <i class="fas fa-box mr-1"></i> Available Releases
                        @if($totalReleases > $maxReleases)
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                (Showing {{ $maxReleases }} of {{ $totalReleases }})
                            </span>
                        @endif
                    </h4>
                    <div class="space-y-3">
                        @foreach($releaseNames as $index => $releaseName)
                            @include('movies.partials.release-item', [
                                'releaseName' => $releaseName,
                                'releaseGuid' => $releaseGuids[$index] ?? '',
                                'releaseId' => $releaseIds[$index] ?? null,
                                'releaseSize' => $releaseSizes[$index] ?? 0,
                                'releasePostDate' => $releasePostDates[$index] ?? null,
                                'releaseAddDate' => $releaseAddDates[$index] ?? null,
                                'hasPreview' => $releaseHasPreview[$index] ?? 0,
                                'jpgStatus' => $releaseJpgStatus[$index] ?? 0,
                                'nfoStatus' => $releaseNfoStatus[$index] ?? 0,
                                'fromName' => $releaseFromNames[$index] ?? '',
                                'layout' => $layout,
                                'index' => $index,
                            ])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>


