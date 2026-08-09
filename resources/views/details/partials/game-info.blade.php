            <!-- Game Information -->
            @if(!empty($game))
                @php
                    $gameData = is_object($game) ? get_object_vars($game) : $game;
                    $gameTitle = $gameData['title'] ?? ($game->title ?? null);
                    $gamePublisher = $gameData['publisher'] ?? ($game->publisher ?? null);
                    $gameReleaseDate = $gameData['releasedate'] ?? ($game->releasedate ?? null);
                    $gameGenres = $gameData['genres'] ?? ($game->genres ?? null);
                @endphp
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-gamepad mr-2 text-primary-600 dark:text-primary-400"></i> Game Information
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
