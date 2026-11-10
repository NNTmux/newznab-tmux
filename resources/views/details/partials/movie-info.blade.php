            {{-- Movie Information (compact: title line with rating chips, then label/value rows) --}}
            @if(!empty($movie))
                @php
                    $movieData = is_object($movie) ? get_object_vars($movie) : $movie;
                    $movieTitle = $movieData['title'] ?? ($movie->title ?? null);
                    $movieYear = $movieData['year'] ?? ($movie->year ?? null);
                    $movieTagline = $movieData['tagline'] ?? ($movie->tagline ?? null);
                    $movieRating = $movieData['rating'] ?? ($movie->rating ?? null);
                    $movieRtRating = $movieData['rtrating'] ?? ($movie->rtrating ?? null);
                    $moviePlot = $movieData['plot'] ?? ($movie->plot ?? null);
                    $movieGenre = $movieData['genre'] ?? ($movie->genre ?? null);
                    $movieDirector = $movieData['director'] ?? ($movie->director ?? null);
                    $movieActors = $movieData['actors'] ?? ($movie->actors ?? null);
                    $movieLanguage = $movieData['language'] ?? ($movie->language ?? null);
                    $movieTrailerUrl = $movieData['trailer_url'] ?? ($movie->trailer_url ?? null);
                    $movieRows = array_filter([
                        'Genre' => $movieGenre,
                        'Director' => $movieDirector,
                        'Cast' => $movieActors,
                    ]);
                @endphp
                <section class="surface-panel-alt rounded-lg border p-5" aria-labelledby="movie-info-heading">
                    <h3 id="movie-info-heading" class="flex items-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                        <i aria-hidden="true" class="fas fa-film mr-2 text-link dark:text-primary-400"></i> Movie Information
                    </h3>

                    @if(!empty($movieTitle))
                        <p class="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ $movieTitle }}@if(!empty($movieYear)) <span class="font-normal text-gray-600 dark:text-gray-400">({{ $movieYear }})</span>@endif
                        </p>
                    @endif

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-medium">
                        @if(!empty($movieRating))
                            <span class="movie-meta-chip movie-meta-chip--rating" title="IMDb rating">
                                <i aria-hidden="true" class="fas fa-star"></i>{{ $movieRating }}/10
                            </span>
                        @endif
                        <x-rotten-tomatoes-score :score="$movieRtRating" size="chip" />
                        @if(!empty($movieLanguage))
                            <span class="movie-meta-chip" title="Language">
                                <i aria-hidden="true" class="fas fa-language"></i>{{ $movieLanguage }}
                            </span>
                        @endif
                    </div>

                    @if(!empty($movieTagline))
                        <p class="mt-3 text-sm italic text-gray-700 dark:text-gray-300">"{{ $movieTagline }}"</p>
                    @endif

                    @if(!empty($moviePlot))
                        <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $moviePlot }}</p>
                    @endif

                    @if($movieRows !== [])
                        <dl class="mt-3 space-y-1.5 text-sm">
                            @foreach($movieRows as $rowLabel => $rowValue)
                                <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3">
                                    <dt class="font-medium text-gray-600 dark:text-gray-400">{{ $rowLabel }}</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{!! $rowValue !!}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if(!empty($movieTrailerUrl))
                        <a href="{{ $site->dereferrerLink ?? '' }}{{ $movieTrailerUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center rounded-lg bg-accent px-3 py-1.5 text-sm font-medium text-white transition hover:bg-accent-hover">
                            <i aria-hidden="true" class="fas fa-circle-play mr-2"></i> Watch trailer
                        </a>
                    @endif
                </section>
            @endif
