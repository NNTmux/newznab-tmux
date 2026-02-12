{{-- Movie Card Partial --}}
{{-- Props: $result (movie object with ->releases array), $layout (1 or 2), $site (site configuration) --}}

@props([
    'result',
    'layout' => 2,
    'site' => [],
])

@php
    // Releases are already attached as an array of objects (max 2 from the query)
    $releases = $result->releases ?? [];
    $totalReleases = $result->total_releases ?? count($releases);
    $maxReleases = 2;

    // Get the first GUID from releases
    $guid = !empty($releases) ? $releases[0]->guid : null;

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
            {{-- Title --}}
            <div class="flex justify-between items-start mb-2">
                <div class="flex-1 min-w-0">
                    @if(isset($result->imdbid) && $result->imdbid)
                        <a href="{{ route('movie.view', ['imdbid' => $result->imdbid]) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ $result->title }}</h3>
                        </a>
                    @else
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ $result->title }}</h3>
                    @endif

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
            @if($guid && !empty($releases))
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
                        @foreach($releases as $index => $release)
                            @if(($release->searchname ?? null) && ($release->guid ?? null))
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                    <div class="release-card-container {{ $layout == 1 ? 'flex flex-row items-start justify-between gap-4' : 'flex flex-col space-y-3' }}">
                                        <div class="release-info-wrapper {{ $layout == 1 ? 'flex-1 min-w-0' : '' }}">
                                            {{-- Release Name --}}
                                            <a href="{{ url('/details/' . $release->guid) }}"
                                               class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium block break-all"
                                               title="{{ $release->searchname }}">
                                                {{ $release->searchname }}
                                            </a>

                                            {{-- Info Badges --}}
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                @if($release->size)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-hdd mr-1"></i>{{ number_format($release->size / 1073741824, 2) }} GB
                                                    </span>
                                                @endif

                                                @if($release->postdate)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-calendar-alt mr-1"></i>{{ userDate($release->postdate, 'M d, Y H:i') }}
                                                    </span>
                                                @endif

                                                @if($release->adddate)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        <i class="fas fa-plus-circle mr-1"></i>{{ userDateDiffForHumans($release->adddate) }}
                                                    </span>
                                                @endif

                                                @if(($release->haspreview ?? 0) == 1)
                                                    <button type="button"
                                                            class="preview-badge inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                                            data-guid="{{ $release->guid }}"
                                                            title="View preview image">
                                                        <i class="fas fa-image mr-1"></i> Preview
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Action Buttons --}}
                                        <div class="release-actions flex flex-wrap items-center gap-2 {{ $layout == 1 ? 'shrink-0' : 'mt-2' }}">
                                            <a href="{{ url('/getnzb/' . $release->guid) }}"
                                               class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-green-600 dark:bg-green-700 text-white hover:bg-green-700 dark:hover:bg-green-800 transition">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>

                                            <button type="button"
                                                    class="add-to-cart inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800 transition"
                                                    data-guid="{{ $release->guid }}">
                                                <i class="fas fa-shopping-cart mr-1"></i> Cart
                                            </button>

                                            <a href="{{ url('/details/' . $release->guid) }}"
                                               class="inline-flex items-center px-3 py-1 rounded text-xs font-medium bg-gray-600 dark:bg-gray-700 text-white hover:bg-gray-700 dark:hover:bg-gray-800 transition">
                                                <i class="fas fa-info-circle mr-1"></i> Details
                                            </a>

                                            @if($release->id)
                                                <x-report-button :release-id="(int)$release->id" variant="icon" />
                                            @endif
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
