                <!-- Advanced - Postprocessing Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced - Postprocessing Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="timeoutseconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-clock mr-1"></i>Time in Seconds to Kill Unrar/7zip/Mediainfo/FFmpeg/Avconv
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="timeoutseconds" name="timeoutseconds" value="{{ $site['timeoutseconds'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">seconds</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">How much time to wait for unrar/7zip/mediainfo/ffmpeg/avconv before killing it, set to 0 to disable. 60 is a good value. Requires the GNU Timeout path to be set.</p>
                        </div>

                        <div>
                            <label for="releaseprocessingtimeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-hourglass-half mr-1"></i>Per-Release Processing Timeout
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="releaseprocessingtimeout" name="releaseprocessingtimeout" value="{{ $site['releaseprocessingtimeout'] ?? '120' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">seconds</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Maximum wall-clock seconds to spend processing a single release before skipping it. Keep this below the multiprocessing child timeout. Set to 0 to disable. Default: 120.</p>
                        </div>

                        <div>
                            <label for="maxpptimeoutcount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-trash-alt mr-1"></i>Max PP Timeout Strikes Before Deletion
                            </label>
                            <input type="text" id="maxpptimeoutcount" name="maxpptimeoutcount" value="{{ $site['maxpptimeoutcount'] ?? '3' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Number of times a release can time out during post-processing before it is permanently deleted. Default: 3.</p>
                        </div>

                        <div>
                            <label for="maxaddprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-list-ol mr-1"></i>Maximum Add PP Per Run
                            </label>
                            <input type="text" id="maxaddprocessed" name="maxaddprocessed" value="{{ $site['maxaddprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Releases claimed per worker batch for passwords, previews, and media info. Larger batches reduce query overhead but increase each worker's memory and runtime. Each thread can hold one NNTP connection.</p>
                        </div>

                        <div>
                            <label for="maxpartsprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-download mr-1"></i>Maximum Add PP Parts Downloaded
                            </label>
                            <input type="text" id="maxpartsprocessed" name="maxpartsprocessed" value="{{ $site['maxpartsprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">If a part fails to download while post processing, this will retry up to the amount you set, then give up.</p>
                        </div>

                        <div>
                            <label for="passchkattempts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-check-double mr-1"></i>Maximum Add PP Parts Checked
                            </label>
                            <input type="text" id="passchkattempts" name="passchkattempts" value="{{ $site['passchkattempts'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">This overrides the above setting if set above 1. How many parts to check for a password before giving up. This slows down post processing massively, better to leave it 1.</p>
                        </div>

                        <div>
                            <label for="maxrageprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tv mr-1"></i>Maximum TV Per Run
                            </label>
                            <input type="text" id="maxrageprocessed" name="maxrageprocessed" value="{{ $site['maxrageprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of TV shows to processper run.</p>
                        </div>

                        <div>
                            <label for="maximdbprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-film mr-1"></i>Maximum Movies Per Run
                            </label>
                            <input type="text" id="maximdbprocessed" name="maximdbprocessed" value="{{ $site['maximdbprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of movies to process with IMDB per run.</p>
                        </div>

                        <div>
                            <label for="maxanidbprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-dragon mr-1"></i>Maximum AniDB Per Run
                            </label>
                            <input type="text" id="maxanidbprocessed" name="maxanidbprocessed" value="{{ $site['maxanidbprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of anime to process with anidb per run.</p>
                        </div>

                        <div>
                            <label for="maxmusicprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-music mr-1"></i>Maximum Music Per Run
                            </label>
                            <input type="text" id="maxmusicprocessed" name="maxmusicprocessed" value="{{ $site['maxmusicprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of music to process with metadata lookups per run. This does not use an NNTP connection.</p>
                        </div>

                        <div>
                            <label for="maxgamesprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-gamepad mr-1"></i>Maximum Games Per Run
                            </label>
                            <input type="text" id="maxgamesprocessed" name="maxgamesprocessed" value="{{ $site['maxgamesprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of games to process with metadata lookups per run. This does not use an NNTP connection.</p>
                        </div>

                        <div>
                            <label for="maxbooksprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-book mr-1"></i>Maximum Books Per Run
                            </label>
                            <input type="text" id="maxbooksprocessed" name="maxbooksprocessed" value="{{ $site['maxbooksprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of books to process with metadata lookups per run. This does not use an NNTP connection</p>
                        </div>

                        <div>
                            <label for="fixnamesperrun" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-edit mr-1"></i>fixReleaseNames Per Run
                            </label>
                            <input type="text" id="fixnamesperrun" name="fixnamesperrun" value="{{ $site['fixnamesperrun'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of releases to check per run (threaded script only).</p>
                        </div>

                        <div>
                            <label for="amazonsleep" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-hourglass mr-1"></i>Metadata Lookup Sleep Time
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="amazonsleep" name="amazonsleep" value="{{ $site['amazonsleep'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">ms</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Sleep time in milliseconds to wait between external metadata requests. If you thread post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, sleep time = 12000</p>
                        </div>
                    </div>
                </div>
