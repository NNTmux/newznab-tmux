                <!-- Lookup Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Lookup Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="lookuptv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tv mr-1"></i>Lookup TV
                            </label>
                            <select id="lookuptv" name="lookuptv" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookuptv['ids'] as $index => $lookuptvId)
                                    <option value="{{ $lookuptvId }}" {{ ($site['lookuptv'] ?? '') == $lookuptvId ? 'selected' : '' }}>
                                        {{ $lookuptv['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup TV related ids on the web.</p>
                        </div>

                        <div>
                            <label for="lookupbooks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-book mr-1"></i>Lookup Books
                            </label>
                            <select id="lookupbooks" name="lookupbooks" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupbooks['ids'] as $index => $lookupbooksId)
                                    <option value="{{ $lookupbooksId }}" {{ ($site['lookupbooks'] ?? '') == $lookupbooksId ? 'selected' : '' }}>
                                        {{ $lookupbooks['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup book information from ISBNdb, with iTunes fallback.</p>
                        </div>

                        <div>
                            <label for="lookupimdb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-film mr-1"></i>Lookup Movies
                            </label>
                            <select id="lookupimdb" name="lookupimdb" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupmovies['ids'] as $index => $lookupmoviesId)
                                    <option value="{{ $lookupmoviesId }}" {{ ($site['lookupimdb'] ?? '') == $lookupmoviesId ? 'selected' : '' }}>
                                        {{ $lookupmovies['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup film information from IMDB or TheMovieDB.</p>
                        </div>

                        <div>
                            <label for="lookuplanguage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-language mr-1"></i>Movie Lookup Language
                            </label>
                            <select id="lookuplanguage" name="lookuplanguage" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookuplanguage['iso'] as $index => $languageIso)
                                    <option value="{{ $languageIso }}" {{ ($site['lookuplanguage'] ?? '') == $languageIso ? 'selected' : '' }}>
                                        {{ $lookuplanguage['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Preferred language for scraping external sources.</p>
                        </div>

                        <div>
                            <label for="lookupanidb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-dragon mr-1"></i>Lookup AniDB
                            </label>
                            <select id="lookupanidb" name="lookupanidb" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['lookupanidb'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup anime information from AniDB when processing binaries.</p>
                        </div>

                        <div>
                            <label for="lookupmusic" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-music mr-1"></i>Lookup Music
                            </label>
                            <select id="lookupmusic" name="lookupmusic" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupmusic['ids'] as $index => $lookupmusicId)
                                    <option value="{{ $lookupmusicId }}" {{ ($site['lookupmusic'] ?? '') == $lookupmusicId ? 'selected' : '' }}>
                                        {{ $lookupmusic['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup music information from metadata providers.</p>
                        </div>

                        <div>
                            <label for="saveaudiopreview" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-music mr-1"></i>Save Audio Preview
                            </label>
                            <select id="saveaudiopreview" name="saveaudiopreview" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['saveaudiopreview'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to save a preview of an audio release (requires deep rar inspection enabled).<br>It is advisable to specify a path to the lame binary to reduce the size of audio previews.</p>
                        </div>

                        <div>
                            <label for="lookupgames" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-gamepad mr-1"></i>Lookup Games
                            </label>
                            <select id="lookupgames" name="lookupgames" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupgames['ids'] as $index => $lookupgamesId)
                                    <option value="{{ $lookupgamesId }}" {{ ($site['lookupgames'] ?? '') == $lookupgamesId ? 'selected' : '' }}>
                                        {{ $lookupgames['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup game information from metadata providers.</p>
                        </div>
                    </div>
                </div>
