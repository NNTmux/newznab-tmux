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

                    // Country model/name are resolved in DetailsController
                    $country = $anidbCountryModel ?? null;
                    $countryName = $anidbCountryName ?? null;

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
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-dragon mr-2 text-primary-600 dark:text-primary-400"></i>
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
                                            <span class="font-mono text-primary-600 dark:text-primary-400">{{ $anidbHashtag }}</span>
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
                                        {{ $anidbMediaType === 'ANIME' ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200' : 'bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200' }}">
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
                                    <a href="{{ $site['dereferrer_link'] ?? '' }}https://anilist.co/anime/{{ $anilistId }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-primary-100 text-primary-800 rounded-lg hover:bg-primary-200 transition dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-800/30">
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
