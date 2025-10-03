@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Release Details</h1>
        <nav class="text-sm text-gray-600">
            <a href="{{ url('/') }}" class="hover:text-blue-600">Home</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span>{{ $release->searchname }}</span>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Cover Image and Title -->
            <div class="border-b border-gray-200 pb-4">
                <div class="flex gap-4 mb-4">
                    <!-- Cover Image -->
                    <div class="flex-shrink-0">
                        <img src="{{ getReleaseCover($release) }}"
                             alt="{{ $release->searchname }}"
                             class="w-48 h-72 object-cover rounded-lg shadow-md max-w-[192px] max-h-[288px]"
                             style="width: 192px; height: 288px;"
                             onerror="this.src='{{ asset('assets/images/no-cover.png') }}'">
                    </div>

                    <!-- Title and Actions -->
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-800 mb-3">{{ $release->searchname }}</h2>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ url('/getnzb/' . $release->guid) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition inline-flex items-center">
                                <i class="fas fa-download mr-2"></i> Download NZB
                            </a>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition inline-flex items-center">
                                <i class="fas fa-shopping-basket mr-2"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>

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
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-film mr-2 text-blue-600"></i> Movie Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($movieTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $movieTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieYear))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Year</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $movieYear }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieTagline))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Tagline</dt>
                                <dd class="mt-1 text-sm text-gray-700 italic">"{{ $movieTagline }}"</dd>
                            </div>
                        @endif
                        @if(!empty($movieRating))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">IMDB Rating</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                                        {{ $movieRating }}/10
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($moviePlot))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Plot Synopsis</dt>
                                <dd class="mt-1 text-sm text-gray-700 leading-relaxed">{{ $moviePlot }}</dd>
                            </div>
                        @endif
                        @if(!empty($movieGenre))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Genre</dt>
                                <dd class="mt-1 text-sm text-gray-900">{!! $movieGenre !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieDirector))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Director</dt>
                                <dd class="mt-1 text-sm text-gray-900">{!! $movieDirector !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieActors))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Cast</dt>
                                <dd class="mt-1 text-sm text-gray-900">{!! $movieActors !!}</dd>
                            </div>
                        @endif
                        @if(!empty($movieLanguage))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Language</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $movieLanguage }}</dd>
                            </div>
                        @endif
                    </div>
                    @if(!empty($movieTrailer))
                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-gray-600 mb-2">Trailer</h4>
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
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-tv mr-2 text-purple-600"></i> TV Show Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($showTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Show Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $showTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($showStarted))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Started</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $showStarted }}</dd>
                            </div>
                        @endif
                        @if(!empty($showTvdb))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">TVDB</dt>
                                <dd class="mt-1">
                                    <a href="https://thetvdb.com/?tab=series&id={{ $showTvdb }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
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
                <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i> Adult Content Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($xxxTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $xxxTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($xxxGenre))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Genre</dt>
                                <dd class="mt-1 text-sm text-gray-900">{!! $xxxGenre !!}</dd>
                            </div>
                        @endif
                        @if(!empty($xxxActors))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Actors</dt>
                                <dd class="mt-1 text-sm text-gray-900">{!! $xxxActors !!}</dd>
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
                <div class="bg-gradient-to-r from-green-50 to-teal-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-music mr-2 text-green-600"></i> Music Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($musicTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Album</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $musicTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicArtist))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Artist</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $musicArtist }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $musicPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $musicReleaseDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($musicGenres))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Genres</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $musicGenres }}</dd>
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
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-orange-600"></i> Game Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($gameTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $gameTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($gamePublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $gamePublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($gameReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $gameReleaseDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($gameGenres))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Genres</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $gameGenres }}</dd>
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
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-indigo-600"></i> Console Game Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($conTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $conTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($conPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $conPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($conReleaseDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Release Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $conReleaseDate }}</dd>
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
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-book mr-2 text-amber-600"></i> Book Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($bookTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $bookTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookAuthor))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Author</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $bookAuthor }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublisher))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Publisher</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $bookPublisher }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookPublishDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Published</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $bookPublishDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($bookOverview))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Overview</dt>
                                <dd class="mt-1 text-sm text-gray-700">{{ $bookOverview }}</dd>
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
                    $anidbType = $anidbData['type'] ?? ($anidb->type ?? null);
                    $anidbStartDate = $anidbData['startdate'] ?? ($anidb->startdate ?? null);
                    $anidbRating = $anidbData['rating'] ?? ($anidb->rating ?? null);
                    $anidbDescription = $anidbData['description'] ?? ($anidb->description ?? null);
                @endphp
                <div class="bg-gradient-to-r from-pink-50 to-purple-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-dragon mr-2 text-pink-600"></i> Anime Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($anidbTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $anidbTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbType))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $anidbType }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbStartDate))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Start Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $anidbStartDate }}</dd>
                            </div>
                        @endif
                        @if(!empty($anidbRating))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Rating</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                                        {{ $anidbRating }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if(!empty($anidbDescription))
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-600">Description</dt>
                                <dd class="mt-1 text-sm text-gray-700">{{ $anidbDescription }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Video/Audio Metadata -->
            @if(!empty($reVideo) || !empty($reAudio) || !empty($reSubs))
                <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-photo-video mr-2 text-gray-600"></i> Media Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if(!empty($reVideo))
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-video mr-2 text-blue-500"></i> Video
                                </h4>
                                @foreach($reVideo as $video)
                                    <div class="text-xs space-y-1">
                                        @if(!empty($video->videocodec))
                                            <p><span class="text-gray-600">Codec:</span> <span class="font-medium">{{ $video->videocodec }}</span></p>
                                        @endif
                                        @if(!empty($video->videowidth) && !empty($video->videoheight))
                                            <p><span class="text-gray-600">Resolution:</span> <span class="font-medium">{{ $video->videowidth }}x{{ $video->videoheight }}</span></p>
                                        @endif
                                        @if(!empty($video->videoaspect))
                                            <p><span class="text-gray-600">Aspect:</span> <span class="font-medium">{{ $video->videoaspect }}</span></p>
                                        @endif
                                        @if(!empty($video->videoduration))
                                            <p><span class="text-gray-600">Duration:</span> <span class="font-medium">{{ $video->videoduration }}</span></p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($reAudio))
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-volume-up mr-2 text-green-500"></i> Audio
                                </h4>
                                @foreach($reAudio as $audio)
                                    <div class="text-xs space-y-1">
                                        @if(!empty($audio->audiocodec))
                                            <p><span class="text-gray-600">Codec:</span> <span class="font-medium">{{ $audio->audiocodec }}</span></p>
                                        @endif
                                        @if(!empty($audio->audiochannels))
                                            <p><span class="text-gray-600">Channels:</span> <span class="font-medium">{{ $audio->audiochannels }}</span></p>
                                        @endif
                                        @if(!empty($audio->audiolanguage))
                                            <p><span class="text-gray-600">Language:</span> <span class="font-medium">{{ $audio->audiolanguage }}</span></p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($reSubs))
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-closed-captioning mr-2 text-purple-500"></i> Subtitles
                                </h4>
                                <div class="text-xs space-y-1">
                                    @foreach($reSubs as $sub)
                                        @if(!empty($sub->subslanguage))
                                            <p class="font-medium">{{ $sub->subslanguage }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- PreDB Information -->
            @if(!empty($predb) && is_array($predb))
                <div class="bg-gradient-to-r from-cyan-50 to-blue-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-database mr-2 text-cyan-600"></i> PreDB Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($predb['title']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $predb['title'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['source']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Source</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $predb['source'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['predate']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Pre Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $predb['predate'] }}</dd>
                            </div>
                        @endif
                        @if(!empty($predb['category']))
                            <div>
                                <dt class="text-sm font-medium text-gray-600">Category</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $predb['category'] }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- NFO -->
            @if($nfo ?? false)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">NFO</h3>
                    <div class="bg-black text-green-400 p-4 rounded-lg overflow-x-auto font-mono text-sm">
                        <pre>{{ $nfo }}</pre>
                    </div>
                </div>
            @endif

            <!-- File List -->
            @if(isset($files) && count($files) > 0)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Files ({{ count($files) }})</h3>
                    <div class="bg-gray-50 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Filename</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Size</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($files as $file)
                                    <tr class="hover:bg-gray-100">
                                        <td class="px-4 py-2 text-sm text-gray-800">{{ $file->name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ number_format($file->size / 1048576, 2) }} MB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Comments -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Comments</h3>
                @if(isset($comments) && count($comments) > 0)
                    <div class="space-y-3">
                        @foreach($comments as $comment)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                            {{ strtoupper(substr($comment->username, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ $comment->username }}</p>
                                            <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($comment->created_at)->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-gray-700">{{ $comment->text }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">No comments yet. Be the first to comment!</p>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-gray-50 rounded-lg p-4 sticky top-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $release->category_name ?? 'Other' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Size</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ number_format($release->size / 1073741824, 2) }} GB</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Files</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $release->totalpart ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Posted</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($release->postdate)->format('M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Group</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $release->group_name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Grabs</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $release->grabs ?? 0 }}</dd>
                    </div>
                    @if($release->imdbid ?? false)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">IMDB</dt>
                            <dd class="mt-1">
                                <a href="https://www.imdb.com/title/tt{{ $release->imdbid }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
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

