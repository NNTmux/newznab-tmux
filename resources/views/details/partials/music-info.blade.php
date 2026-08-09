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
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-music mr-2 text-primary-600 dark:text-primary-400"></i> Music Information
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
