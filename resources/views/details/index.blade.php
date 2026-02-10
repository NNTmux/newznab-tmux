@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-2">Release Details</h1>
        <nav class="text-sm text-gray-600">
            <a href="{{ url('/') }}" class="hover:text-blue-600">Home</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span class="break-words break-all">{{ $release->searchname }}</span>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Cover Image and Title -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                <div class="flex gap-4 mb-4">
                    <!-- Cover Image -->
                    <div class="flex-shrink-0">
                        <img src="{{ getReleaseCover($release) }}"
                             alt="{{ $release->searchname }}"
                             class="w-48 h-72 object-cover rounded-lg shadow-md max-w-[192px] max-h-[288px]"
                             class="rounded-lg shadow-lg object-cover w-192 h-288"
                             data-fallback-src="{{ asset('assets/images/no-cover.png') }}">
                    </div>

                    <!-- Title and Actions -->
                    <div class="flex-1">
                        <div class="mb-3">
                            <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2 break-words break-all">{{ $release->searchname }}</h2>
                            <div class="flex flex-wrap gap-2">
                                @if(!empty($reportCount) && $reportCount > 0)
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 border border-orange-200 dark:border-orange-800"
                                         title="Reported: {{ $reportReasons ?? 'Unknown' }}">
                                        <i class="fas fa-flag mr-2"></i>
                                        <span>{{ $reportCount }} report{{ $reportCount > 1 ? 's' : '' }}: {{ $reportReasons ?? 'Unknown' }}</span>
                                    </div>
                                @endif
                                @if(!empty($failed) && $failed > 0)
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-red-100 text-red-800 border border-red-200">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span>{{ $failed }} user{{ $failed > 1 ? 's' : '' }} reported download failure</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ url('/getnzb/' . $release->guid) }}" class="download-nzb px-4 py-2 bg-green-600 dark:bg-green-700 text-white rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition inline-flex items-center">
                                <i class="fas fa-download mr-2"></i> Download NZB
                            </a>
                            <a href="#" class="add-to-cart px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition inline-flex items-center" data-guid="{{ $release->guid }}">
                                <i class="icon_cart fas fa-shopping-basket mr-2"></i> Add to Cart
                            </a>
                            @if(isset($release->nfostatus) && $release->nfostatus == 1)
                                <button type="button" class="nfo-badge px-4 py-2 bg-yellow-600 dark:bg-yellow-700 text-white rounded-lg hover:bg-yellow-700 dark:hover:bg-yellow-800 transition inline-flex items-center" data-guid="{{ $release->guid }}" title="View NFO file">
                                    <i class="fas fa-file-alt mr-2"></i> View NFO
                                </button>
                            @endif
                            @auth
                                @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Moderator'))
                                    <a href="{{ route('admin.release-edit', ['id' => $release->guid]) }}" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition inline-flex items-center" title="Edit Release">
                                        <i class="fas fa-edit mr-2"></i> Edit Release
                                    </a>
                                @endif
                            @endauth
                            <x-report-button :release-id="$release->id" variant="button-lg" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sample/Preview Images -->
            @php
                $hasPreviewImage = isset($release->haspreview) && $release->haspreview == 1;
                $hasSampleImage = isset($release->jpgstatus) && $release->jpgstatus == 1;
            @endphp

            @if($hasPreviewImage || $hasSampleImage)
                <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                        <i class="fas fa-images mr-2 text-purple-600"></i>
                        @if($hasPreviewImage && $hasSampleImage)
                            Preview & Sample Images
                        @elseif($hasPreviewImage)
                            Preview Image
                        @else
                            Sample Image
                        @endif
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @if($hasPreviewImage)
                            <!-- Preview image -->
                            <div>
                                <div class="block cursor-pointer image-modal-trigger" data-image-url="{{ url('/covers/preview/' . $release->guid . '_thumb.jpg') }}" data-image-title="Preview Image">
                                    <img src="{{ url('/covers/preview/' . $release->guid . '_thumb.jpg') }}"
                                         alt="Preview"
                                         class="w-full h-auto rounded-lg shadow-md hover:shadow-lg transition"
                                         loading="lazy">
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-center">Preview</p>
                            </div>
                        @endif

                        @if($hasSampleImage)
                            <!-- Sample image -->
                            <div>
                                <div class="block cursor-pointer image-modal-trigger" data-image-url="{{ url('/covers/sample/' . $release->guid . '_thumb.jpg') }}" data-image-title="Sample Image">
                                    <img src="{{ url('/covers/sample/' . $release->guid . '_thumb.jpg') }}"
                                         alt="Sample"
                                         class="w-full h-auto rounded-lg shadow-md hover:shadow-lg transition"
                                         loading="lazy">
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-center">Sample</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Movie Information -->
            @if(!empty($movie))
                @php
                    $movieData = is_object($movie) ? get_object_vars($movie) : $movie;
                    $movieTitle = $movieData['title'] ?? ($movie->title ?? null);
                    $movieYear = $movieData['year'] ?? ($movie->year ?? null);
                    $movieTagline = $movieData['tagline'] ?? ($movie->tagline ?? null);
                    $movieRating = $movieData['rating'] ?? ($movie->rating ?? null);
                    $moviePlot = $movieData['plot'] ?? ($movie->plot ?? null);
                    $movieGenre = $movieData['genre'] ?? ($movie->genre ?? null);
                    $movieDirector = $movieData['director'] ?? ($movie->director ?? null);
                    $movieActors = $movieData['actors'] ?? ($movie->actors ?? null);
                    $movieLanguage = $movieData['language'] ?? ($movie->language ?? null);
                    $movieTrailer = $movieData['trailer'] ?? ($movie->trailer ?? null);
                @endphp
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-6 border border-blue-100 dark:border-blue-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-film mr-2 text-blue-600 dark:text-blue-400"></i> Movie Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($movieTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $movieTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieYear))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Year</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $movieYear }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieTagline))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Tagline</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300 italic">"{{ $movieTagline }}"</dd>
                            </div>
                        @endif
                        @if(!empty($movieRating))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">IMDB Rating</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-star text-yellow-500 dark:text-yellow-400 mr-1"></i>
                                        {{ $movieRating }}/10
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($moviePlot))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Plot Synopsis</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $moviePlot }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieGenre))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Genre</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! $movieGenre !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieDirector))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Director</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! $movieDirector !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieActors))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Cast</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! $movieActors !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieLanguage))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Language</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $movieLanguage }}</dd>
                            </div>
                        @endif
                    </div>
                    @if(!empty($movieTrailer))
                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Trailer</h4>
                            <div class="aspect-video">
                                {!! $movieTrailer !!}
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- TV Show Information -->
            @if(!empty($show))
                @php
                    $showData = is_object($show) ? get_object_vars($show) : $show;
                    $showTitle = $showData['title'] ?? ($show->title ?? null);
                    $showStarted = $showData['started'] ?? ($show->started ?? null);
                    $showTvdb = $showData['tvdb'] ?? ($show->tvdb ?? null);
                @endphp
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-lg p-6 border border-purple-100 dark:border-purple-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-tv mr-2 text-purple-600 dark:text-purple-400"></i> TV Show Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($showTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Show Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $showTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($showStarted))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Started</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $showStarted }}</dd>
                            </div>
                        @endif
                        @if(!empty($showTvdb))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">TVDB</dt>
                                <dd class="mt-1">
                                    <a href="{{ $site['dereferrer_link'] }}https://thetvdb.com/?tab=series&id={{ $showTvdb }}" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        View on TVDB <i class="fas fa-external-link-alt text-xs"></i>
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- XXX Information -->
            @if(!empty($xxx))
                @php
                    $xxxData = is_object($xxx) ? get_object_vars($xxx) : $xxx;
                    $xxxTitle = $xxxData['title'] ?? ($xxx->title ?? null);
                    $xxxGenre = $xxxData['genre'] ?? ($xxx->genre ?? null);
                    $xxxActors = $xxxData['actors'] ?? ($xxx->actors ?? null);
                @endphp
                <div class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900 dark:to-pink-900 rounded-lg p-6 border border-red-100 dark:border-red-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2 text-red-600 dark:text-red-400"></i> Adult Content Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($xxxTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $xxxTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($xxxGenre))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Genre</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! $xxxGenre !!}</dd>
                            </div>
                        @endif
                        @if(!empty($xxxActors))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Actors</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! $xxxActors !!}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Music Information -->
            @if(!empty($music))
                @php
                    $musicData = is_object($music) ? get_object_vars($music) : $music;
                    $musicTitle = $musicData['title'] ?? ($music->title ?? null);
                    $musicArtist = $musicData['artist'] ?? ($music->artist ?? null);
                    $musicPublisher = $musicData['publisher'] ?? ($music->publisher ?? null);
                    $musicReleaseDate = $musicData['releasedate'] ?? ($music->releasedate ?? null);
                    $musicGenres = $musicData['genres'] ?? ($music->genres ?? null);
                @endphp
                <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-6 border border-green-100 dark:border-green-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-music mr-2 text-green-600 dark:text-green-400"></i> Music Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($musicTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Album</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $musicTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicArtist))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Artist</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $musicArtist }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $musicPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $musicReleaseDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicGenres))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Genres</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $musicGenres }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Game Information -->
            @if(!empty($game))
                @php
                    $gameData = is_object($game) ? get_object_vars($game) : $game;
                    $gameTitle = $gameData['title'] ?? ($game->title ?? null);
                    $gamePublisher = $gameData['publisher'] ?? ($game->publisher ?? null);
                    $gameReleaseDate = $gameData['releasedate'] ?? ($game->releasedate ?? null);
                    $gameGenres = $gameData['genres'] ?? ($game->genres ?? null);
                @endphp
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 dark:from-orange-900 dark:to-yellow-900 rounded-lg p-6 border border-orange-100 dark:border-orange-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-orange-600 dark:text-orange-400"></i> Game Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($gameTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $gameTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($gamePublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $gamePublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($gameReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $gameReleaseDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($gameGenres))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Genres</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $gameGenres }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Console Information -->
            @if(!empty($con))
                @php
                    $conData = is_object($con) ? get_object_vars($con) : $con;
                    $conTitle = $conData['title'] ?? ($con->title ?? null);
                    $conPublisher = $conData['publisher'] ?? ($con->publisher ?? null);
                    $conReleaseDate = $conData['releasedate'] ?? ($con->releasedate ?? null);
                @endphp
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-900 dark:to-blue-900 rounded-lg p-6 border border-indigo-100 dark:border-indigo-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-indigo-600 dark:text-indigo-400"></i> Console Game Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($conTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $conTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($conPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $conPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($conReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $conReleaseDate }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Book Information -->
            @if(!empty($book))
                @php
                    $bookData = is_object($book) ? get_object_vars($book) : $book;
                    $bookTitle = $bookData['title'] ?? ($book->title ?? null);
                    $bookAuthor = $bookData['author'] ?? ($book->author ?? null);
                    $bookPublisher = $bookData['publisher'] ?? ($book->publisher ?? null);
                    $bookPublishDate = $bookData['publishdate'] ?? ($book->publishdate ?? null);
                    $bookOverview = $bookData['overview'] ?? ($book->overview ?? null);
                @endphp
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900 dark:to-orange-900 rounded-lg p-6 border border-amber-100 dark:border-amber-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-book mr-2 text-amber-600 dark:text-amber-400"></i> Book Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($bookTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $bookTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookAuthor))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Author</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookAuthor }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublishDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Published</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $bookPublishDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookOverview))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Overview</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $bookOverview }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Anime Information -->
            @if(!empty($anidb))
                @php
                    $anidbData = is_object($anidb) ? get_object_vars($anidb) : $anidb;
                    $anidbTitle = $anidbData['title'] ?? ($anidb->title ?? null);
                    $anidbEnglishTitle = $anidbData['english_title'] ?? ($anidb->english_title ?? null);
                    $anidbOriginalTitle = $anidbData['original_title'] ?? ($anidb->original_title ?? null);
                    $anidbOriginalLang = $anidbData['original_lang'] ?? ($anidb->original_lang ?? null);
                    $anidbRomajiTitle = $anidbData['romaji_title'] ?? ($anidb->romaji_title ?? null);
                    $anidbHashtag = $anidbData['hashtag'] ?? ($anidb->hashtag ?? null);
                    $anidbType = $anidbData['type'] ?? ($anidb->type ?? null);
                    $anidbMediaType = $anidbData['media_type'] ?? ($anidb->media_type ?? null);
                    $anidbCountry = $anidbData['country'] ?? ($anidb->country ?? null);
                    $anidbEpisodes = $anidbData['episodes'] ?? ($anidb->episodes ?? null);
                    $anidbDuration = $anidbData['duration'] ?? ($anidb->duration ?? null);
                    $anidbStatus = $anidbData['status'] ?? ($anidb->status ?? null);
                    $anidbSource = $anidbData['source'] ?? ($anidb->source ?? null);
                    $anidbStartDate = $anidbData['startdate'] ?? ($anidb->startdate ?? null);
                    $anidbEndDate = $anidbData['enddate'] ?? ($anidb->enddate ?? null);
                    $anidbRating = $anidbData['rating'] ?? ($anidb->rating ?? null);
                    $anidbDescription = $anidbData['description'] ?? ($anidb->description ?? null);
                    $anidbCategories = $anidbData['categories'] ?? ($anidb->categories ?? null);
                    $anidbCreators = $anidbData['creators'] ?? ($anidb->creators ?? null);

                    // Get country name from country code
                    $countryName = null;
                    if (!empty($anidbCountry)) {
                        $country = \App\Models\Country::where('iso_3166_2', $anidbCountry)->first();
                        $countryName = $country->name ?? $anidbCountry;
                    }

                    // Format status
                    $statusLabels = [
                        'FINISHED' => 'Finished',
                        'RELEASING' => 'Releasing',
                        'NOT_YET_RELEASED' => 'Not Yet Released',
                        'CANCELLED' => 'Cancelled',
                        'HIATUS' => 'Hiatus',
                    ];
                    $anidbStatusLabel = $statusLabels[$anidbStatus] ?? $anidbStatus;

                    // Format source
                    $sourceLabels = [
                        'ORIGINAL' => 'Original',
                        'MANGA' => 'Manga',
                        'LIGHT_NOVEL' => 'Light Novel',
                        'VISUAL_NOVEL' => 'Visual Novel',
                        'VIDEO_GAME' => 'Video Game',
                        'OTHER' => 'Other',
                        'NOVEL' => 'Novel',
                        'DOUJINSHI' => 'Doujinshi',
                        'ANIME' => 'Anime',
                        'WEB_MANGA' => 'Web Manga',
                        'MUSIC' => 'Music',
                        '4_KOMA_MANGA' => '4-Koma Manga',
                    ];
                    $anidbSourceLabel = $sourceLabels[$anidbSource] ?? $anidbSource;
                @endphp
                <div class="bg-gradient-to-r from-pink-50 to-purple-50 dark:from-pink-900 dark:to-purple-900 rounded-lg p-6 border border-pink-100 dark:border-pink-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-dragon mr-2 text-pink-600 dark:text-pink-400"></i>
                        @if($anidbMediaType === 'MANGA')
                            Manga Information
                        @else
                            Anime Information
                        @endif
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($anidbEnglishTitle) || !empty($anidbOriginalTitle) || !empty($anidbRomajiTitle) || !empty($anidbHashtag))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Titles</dt>
                                <dd class="mt-1 space-y-2">
                                    @if(!empty($anidbEnglishTitle))
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">English:</span>
                                            {{ $anidbEnglishTitle }}
                                        </div>
                                    @endif
                                    @if(!empty($anidbOriginalTitle) && $anidbOriginalLang === 'ja')
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">Native:</span>
                                            {{ $anidbOriginalTitle }}
                                        </div>
                                    @endif
                                    @if(!empty($anidbRomajiTitle))
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">Romaji:</span>
                                            {{ $anidbRomajiTitle }}
                                        </div>
                                    @elseif(!empty($anidbOriginalTitle) && $anidbOriginalLang === 'x-jat')
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">Romaji:</span>
                                            {{ $anidbOriginalTitle }}
                                        </div>
                                    @elseif(!empty($anidbOriginalTitle) && $anidbOriginalLang !== 'ja')
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">Original:</span>
                                            {{ $anidbOriginalTitle }}
                                        </div>
                                    @endif
                                    @if(!empty($anidbHashtag))
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal mr-2">Hashtag:</span>
                                            <span class="font-mono text-blue-600 dark:text-blue-400">{{ $anidbHashtag }}</span>
                                        </div>
                                    @endif
                                    @if(empty($anidbEnglishTitle) && empty($anidbOriginalTitle) && empty($anidbRomajiTitle) && !empty($anidbTitle))
                                        <div class="text-sm text-gray-900 dark:text-gray-100 font-semibold">
                                            {{ $anidbTitle }}
                                        </div>
                                    @endif
                                </dd>
                            </div>
                        @elseif(!empty($anidbTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $anidbTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbMediaType))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Media Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ $anidbMediaType === 'ANIME' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                        {{ $anidbMediaType === 'ANIME' ? 'Anime' : 'Manga' }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($anidbType))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Format</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('_', ' ', strtolower($anidbType))) }}</dd>
                            </div>
                        @endif
                        @if(!empty($countryName))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Country</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center">
                                        @if(!empty($country))
                                            <img src="{{ asset('assets/images/flags/' . strtolower($anidbCountry) . '.png') }}"
                                                 alt="{{ $countryName }}"
                                                 class="w-4 h-3 mr-1"
                                                 data-hide-on-error="true">
                                        @endif
                                        {{ $countryName }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($anidbStatus))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbStatusLabel }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbSource))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Source</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbSourceLabel }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbEpisodes))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Episodes</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($anidbEpisodes) }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbDuration))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Duration</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbDuration }} minutes</dd>
                            </div>
                        @endif
                        @if(!empty($anidbStartDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Start Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbStartDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbEndDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">End Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbEndDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbRating))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Rating</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-star text-yellow-500 dark:text-yellow-400 mr-1"></i>
                                        @php
                                            $ratingValue = is_numeric($anidbRating) ? (float)$anidbRating : 0;
                                            // AniList rating is out of 100, convert to /10
                                            $displayRating = $ratingValue > 10 ? number_format($ratingValue / 10, 1) : number_format($ratingValue, 1);
                                        @endphp
                                        {{ $displayRating }} / 10
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($anidbCategories))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Genres</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbCategories }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbCreators))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Studios</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $anidbCreators }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbDescription))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</dt>
                                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $anidbDescription }}</dd>
                            </div>
                        @endif
                    </div>

                    <!-- External Links -->
                    @php
                        $anilistId = $anidbData['anilist_id'] ?? ($anidb->anilist_id ?? null);
                        $malId = $anidbData['mal_id'] ?? ($anidb->mal_id ?? null);
                    @endphp
                    @if(!empty($anilistId) || !empty($malId))
                        <div class="mt-4 pt-4 border-t border-pink-200 dark:border-pink-700">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">External Links</h4>
                            <div class="flex flex-wrap gap-3">
                                @if(!empty($anilistId))
                                    <a href="{{ $site['dereferrer_link'] ?? '' }}https://anilist.co/anime/{{ $anilistId }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition">
                                        <i class="fas fa-external-link-alt mr-2"></i> View on AniList
                                    </a>
                                @endif
                                @if(!empty($malId))
                                    <a href="{{ $site['dereferrer_link'] ?? '' }}https://myanimelist.net/anime/{{ $malId }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition">
                                        <i class="fas fa-external-link-alt mr-2"></i> View on MyAnimeList
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Password Information -->
            @if(isset($release->passwordstatus) && $release->passwordstatus > 0)
                <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900 dark:to-orange-900 rounded-lg p-6 border border-red-100 dark:border-red-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-lock mr-2 text-red-600 dark:text-red-400"></i> Password Protected Release
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Password Status</dt>
                            <dd class="mt-1">
                                @if($release->passwordstatus == 1)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <i class="fas fa-question-circle mr-1"></i> Password Unknown
                                    </span>
                                @elseif($release->passwordstatus == 2)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-check-circle mr-1"></i> Password Available
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Password</dt>
                            <dd class="mt-1">
                                @if(!empty($release->password))
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-300 dark:border-gray-700">
                                        <code class="text-sm text-gray-900 dark:text-gray-100 font-mono break-all">{{ $release->password }}</code>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        This password is embedded in the NZB file and will be automatically recognized by NZBGet or SABnzbd.
                                    </p>
                                @else
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-300 dark:border-gray-700">
                                        <code class="text-sm text-gray-500 dark:text-gray-400 font-mono">None</code>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        No password information available in the database.
                                    </p>
                                @endif
                            </dd>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Video/Audio Metadata -->
            @if(!empty($reVideo) || !empty($reAudio) || !empty($reSubs))
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-photo-video mr-2 text-blue-600 dark:text-blue-400"></i> Media Information
                    </h3>

                    @if(!empty($reVideo))
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-video mr-2 text-blue-500 dark:text-blue-400"></i> Video Details
                            </h4>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                                <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    @if(!empty($reVideo['containerformat']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Container Format</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['containerformat'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videocodec']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Video Codec</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videocodec'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoformat']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Video Format</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoformat'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videowidth']) && !empty($reVideo['videoheight']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Resolution</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videowidth'] }}x{{ $reVideo['videoheight'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoaspect']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Aspect Ratio</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoaspect'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoframerate']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Frame Rate</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoframerate'] }} fps</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoduration']))
                                        @php
                                            $durationMs = intval($reVideo['videoduration']);
                                            $durationMinutes = $durationMs > 0 ? round($durationMs / 1000 / 60) : 0;
                                        @endphp
                                        @if($durationMinutes > 0)
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Duration</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $durationMinutes }} minutes</dd>
                                            </div>
                                        @endif
                                    @endif
                                    @if(!empty($reVideo['overallbitrate']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bit Rate</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['overallbitrate'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videolibrary']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Encoder Library</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videolibrary'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    @endif

                    @if(!empty($reAudio))
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-volume-up mr-2 text-green-500 dark:text-green-400"></i> Audio Details
                            </h4>
                            @foreach($reAudio as $index => $audio)
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700 {{ $index > 0 ? 'mt-3' : '' }}">
                                    @if(count($reAudio) > 1)
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Track {{ $index + 1 }}</p>
                                    @endif
                                    <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                        @if(!empty($audio['audioformat']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Audio Format</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audioformat'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiocodec']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Codec</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiocodec'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiochannels']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Channels</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiochannels'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiobitrate']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bit Rate</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiobitrate'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiolanguage']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Language</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiolanguage'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiosamplerate']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Sample Rate</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiosamplerate'] }} Hz</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($reSubs))
                        <div>
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-closed-captioning mr-2 text-purple-500"></i> Subtitles
                            </h4>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                <p class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reSubs->subs }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- PreDB Information -->
            @if(!empty($predb) && is_array($predb))
                <div class="bg-gradient-to-r from-cyan-50 to-blue-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-database mr-2 text-cyan-600"></i> PreDB Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($predb['title']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $predb['title'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['source']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Source</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['source'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['predate']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Pre Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['predate'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['category']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Category</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $predb['category'] }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- NFO -->
            @if($nfo ?? false)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">NFO</h3>
                    <div class="bg-black dark:bg-gray-950 text-green-400 dark:text-green-300 p-4 rounded-lg overflow-x-auto font-mono text-sm border border-gray-700 dark:border-gray-600">
                        <pre>{{ $nfo }}</pre>
                    </div>
                </div>
            @endif

            <!-- File List -->
            @if(isset($files) && count($files) > 0)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Files ({{ count($files) }})</h3>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300">Filename</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 dark:text-gray-300">Size</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @foreach($files as $file)
                                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $file->name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ number_format($file->size / 1048576, 2) }} MB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Comments Section -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-comments mr-2 text-blue-600 dark:text-blue-400"></i>
                    Comments ({{ isset($comments) ? count($comments) : 0 }})
                </h3>

                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-green-800 dark:text-green-200 flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ session('success') }}
                        </p>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                        <p class="text-sm text-red-800 dark:text-red-200 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            {{ session('error') }}
                        </p>
                    </div>
                @endif

                <!-- Add Comment Form -->
                @auth
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <form method="POST" action="{{ url('/details/' . $release->guid) }}" id="commentForm">
                            @csrf
                            <div class="mb-3">
                                <label for="txtAddComment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Add a Comment
                                </label>
                                <textarea
                                    name="txtAddComment"
                                    id="txtAddComment"
                                    rows="4"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                                    placeholder="Share your thoughts about this release..."
                                    required
                                ></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition inline-flex items-center font-medium shadow-sm"
                                >
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Please <a href="{{ route('login') }}" class="font-semibold underline hover:text-yellow-900 dark:hover:text-yellow-100">log in</a> to add a comment.
                        </p>
                    </div>
                @endauth

                <!-- Comments List -->
                @if(isset($comments) && count($comments) > 0)
                    <div class="space-y-4">
                        @foreach($comments as $comment)
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-700 dark:to-blue-800 rounded-full flex items-center justify-center text-white font-bold mr-3 shadow-sm">
                                            {{ strtoupper(substr($comment['username'] ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $comment['username'] ?? 'Anonymous' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <i class="far fa-clock mr-1"></i>
                                                {{ \Carbon\Carbon::parse($comment['created_at'])->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $comment['text'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-8 border border-gray-200 dark:border-gray-700 text-center">
                        <i class="fas fa-comments text-4xl text-gray-400 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 sticky top-4 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->category_name ?? 'Other' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Size</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($release->size / 1073741824, 2) }} GB</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Files</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->totalpart ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Added</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ userDate($release->adddate, 'M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Group</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->group_name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ userDate($release->postdate, 'M d, Y H:i') }}</dd>
                    </div>
                    @if(!empty($release->fromname))
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted By</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 break-all">
                                <span class="inline-flex items-center px-2 py-1 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 text-xs font-mono">
                                    <i class="fas fa-user mr-1"></i>{{ $release->fromname }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Grabs</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->grabs ?? 0 }}</dd>
                    </div>
                    @if($release->imdbid ?? false)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IMDB</dt>
                            <dd class="mt-1">
                                <a href="{{ $site['dereferrer_link'] }}https://www.imdb.com/title/tt{{ $release->imdbid }}" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    View on IMDB <i class="fas fa-external-link-alt text-xs"></i>
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

<!-- Image Modal -->
<div id="imageModal" class="image-modal-backdrop hidden modal-hidden">
    <div class="image-modal-container">
        <button type="button" class="image-modal-close" data-close-image-modal>
            <i class="fas fa-times"></i>
        </button>
        <div class="text-center">
            <h3 id="imageModalTitle" class="text-white text-xl font-semibold mb-4 drop-shadow-lg"></h3>
            <img id="imageModalImage" src="" alt="Image" class="max-w-full max-h-[85vh] mx-auto rounded-2xl shadow-2xl">
        </div>
    </div>
</div>

{{-- NFO modal is included globally via layouts.main --}}

@push('scripts')
@include('partials.cart-script')
@endpush

