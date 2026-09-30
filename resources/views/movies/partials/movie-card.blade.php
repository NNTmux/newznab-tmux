@props([
    'result',
    'site' => [],
])

@php
    $releases = $result->releases ?? [];
    $totalReleases = (int) ($result->total_releases ?? count($releases));
    $guid = !empty($releases) ? ($releases[0]->guid ?? null) : null;
    $movieUrl = !empty($result->imdbid)
        ? route('movie.view', ['imdbid' => $result->imdbid])
        : ($guid ? url('/details/' . $guid) : null);
@endphp

<article class="movie-card surface-panel overflow-hidden rounded-2xl border shadow-sm">
    <div class="movie-card__layout">
        <div class="movie-card__poster-wrap">
            @if($movieUrl)
                <a href="{{ $movieUrl }}" class="movie-card__poster-link" aria-label="View {{ $result->title }}">
            @endif

            @if(!empty($result->cover))
                <img src="{{ $result->cover }}"
                     alt="{{ $result->title }} poster"
                     class="movie-cover"
                     width="320"
                     height="480"
                     loading="lazy"
                     decoding="async">
            @else
                <div class="movie-cover movie-card__poster-placeholder" role="img" aria-label="No poster available for {{ $result->title }}">
                    <i class="fas fa-clapperboard" aria-hidden="true"></i>
                    <span>No poster</span>
                </div>
            @endif

            @if($movieUrl)
                </a>
            @endif

            <span class="movie-card__release-count">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                {{ $totalReleases }} {{ Str::plural('release', $totalReleases) }}
            </span>
        </div>

        <div class="movie-card__content min-w-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if($movieUrl)
                            <a href="{{ $movieUrl }}" class="group/title rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                <h3 class="movie-card__title text-xl font-bold tracking-tight text-gray-900 transition group-hover/title:text-primary-600 dark:text-gray-100 dark:group-hover/title:text-primary-400">
                                    {{ $result->title }}
                                </h3>
                            </a>
                        @else
                            <h3 class="movie-card__title text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ $result->title }}</h3>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-medium">
                            @if(!empty($result->year))
                                <span class="movie-meta-chip">
                                    <i class="fas fa-calendar" aria-hidden="true"></i>{{ $result->year }}
                                </span>
                            @endif
                            @if(!empty($result->rating))
                                <span class="movie-meta-chip movie-meta-chip--rating">
                                    <i class="fas fa-star" aria-hidden="true"></i>{{ $result->rating }} / 10
                                </span>
                            @endif
                            @if(!empty($result->latest_postdate))
                                <span class="movie-meta-chip">
                                    <i class="fas fa-clock" aria-hidden="true"></i>Updated {{ userDateDiffForHumans($result->latest_postdate) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($result->plot))
                    <p class="mt-4 line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $result->plot }}</p>
                @endif

                @if(!empty($result->genre) || !empty($result->director) || !empty($result->actors))
                    <dl class="movie-card__metadata mt-4 grid gap-2 text-sm">
                        @if(!empty($result->genre))
                            <div class="movie-card__metadata-row">
                                <dt><i class="fas fa-masks-theater" aria-hidden="true"></i><span>Genre</span></dt>
                                <dd>{!! $result->genre !!}</dd>
                            </div>
                        @endif
                        @if(!empty($result->director))
                            <div class="movie-card__metadata-row">
                                <dt><i class="fas fa-video" aria-hidden="true"></i><span>Director</span></dt>
                                <dd>{!! $result->director !!}</dd>
                            </div>
                        @endif
                        @if(!empty($result->actors))
                            <div class="movie-card__metadata-row">
                                <dt><i class="fas fa-users" aria-hidden="true"></i><span>Cast</span></dt>
                                <dd class="line-clamp-2">{!! $result->actors !!}</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="External movie links">
                    @if(!empty($result->imdbid))
                        <a href="{{ ($site->dereferrerLink ?? '') }}https://www.imdb.com/title/tt{{ $result->imdbid }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="movie-source-link movie-source-link--imdb">
                            <i class="fab fa-imdb" aria-hidden="true"></i>IMDb<span class="sr-only"> (opens in a new tab)</span>
                        </a>
                    @endif
                    @if(!empty($result->tmdbid))
                        <a href="{{ ($site->dereferrerLink ?? '') }}https://www.themoviedb.org/movie/{{ $result->tmdbid }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="movie-source-link movie-source-link--tmdb">
                            <i class="fas fa-film" aria-hidden="true"></i>TMDb<span class="sr-only"> (opens in a new tab)</span>
                        </a>
                    @endif
                    @if(!empty($result->traktid))
                        <a href="{{ ($site->dereferrerLink ?? '') }}https://trakt.tv/movies/{{ $result->traktid }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="movie-source-link movie-source-link--trakt">
                            <i class="fas fa-heart" aria-hidden="true"></i>Trakt<span class="sr-only"> (opens in a new tab)</span>
                        </a>
                    @endif
                </div>
            </div>

            @if($guid && !empty($releases))
                <section class="movie-card__releases mt-5 border-t border-gray-200 pt-4 dark:border-gray-700" aria-label="Available releases for {{ $result->title }}">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            <i class="fas fa-box-open mr-1.5 text-primary-500" aria-hidden="true"></i>Latest releases
                        </h4>
                        @if($totalReleases > count($releases) && !empty($result->imdbid))
                            <a href="{{ route('movie.view', ['imdbid' => $result->imdbid]) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                View all {{ $totalReleases }} <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>

                    <div class="grid gap-2.5">
                        @foreach($releases as $index => $release)
                            @include('movies.partials.release-item', ['release' => $release, 'index' => $index])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</article>
