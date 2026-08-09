                <!-- Advanced Settings - For advanced users -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced Settings - For Advanced Users</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="maxnzbsprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-code mr-1"></i>Maximum NZBs Stage5
                            </label>
                            <input type="text" id="maxnzbsprocessed" name="maxnzbsprocessed" value="{{ $site['maxnzbsprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of NZB files to create on stage 5 at a time in update_releases. If more are to be created it will loop stage 5 until none remain.</p>
                        </div>

                        <div>
                            <label for="partrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-toolbox mr-1"></i>Part Repair
                            </label>
                            <select id="partrepair" name="partrepair" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['partrepair'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to repair parts or not, increases backfill/binaries updating time.</p>
                        </div>

                        <div>
                            <label for="safepartrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-shield-alt mr-1"></i>Part Repair for Backfill Scripts
                            </label>
                            <select id="safepartrepair" name="safepartrepair" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['safepartrepair'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to put unreceived parts into missed_parts table when running binaries(safe) or backfill scripts.</p>
                        </div>

                        <div>
                            <label for="maxpartrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tools mr-1"></i>Maximum Repair Per Run
                            </label>
                            <input type="text" id="maxpartrepair" name="maxpartrepair" value="{{ $site['maxpartrepair'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of articles to attempt to repair at a time. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads to safe scripts or stop using safe scripts until improves.</p>
                        </div>

                        <div>
                            <label for="partrepairmaxtries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-redo mr-1"></i>Maximum Repair Tries
                            </label>
                            <input type="text" id="partrepairmaxtries" name="partrepairmaxtries" value="{{ $site['partrepairmaxtries'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Maximum amount of times to try part repair.</p>
                        </div>

                        <div>
                            <label for="processjpg" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-image mr-1"></i>Process JPG
                            </label>
                            <select id="processjpg" name="processjpg" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processjpg'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to retrieve a JPG file while additional post processing, these are usually on XXX releases.</p>
                        </div>

                        <div>
                            <label for="processthumbnails" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-image mr-1"></i>Process Video Thumbnails
                            </label>
                            <select id="processthumbnails" name="processthumbnails" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processthumbnails'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to process a video thumbnail image. You must have ffmpeg for this.</p>
                        </div>

                        <div>
                            <label for="processvideos" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-film mr-1"></i>Process Video Samples
                            </label>
                            <select id="processvideos" name="processvideos" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processvideos'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to process a video sample, these videos are very short 1-3 seconds, 100KB on average, in ogg video format. You must have ffmpeg for this.</p>
                        </div>

                        <div>
                            <label for="segmentstodownload" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-download mr-1"></i>Number of Segments to Download
                            </label>
                            <input type="text" id="segmentstodownload" name="segmentstodownload" value="{{ $site['segmentstodownload'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of segments to download to generate the sample video file or jpg sample image. (Default 2)</p>
                        </div>

                        <div>
                            <label for="ffmpeg_duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-film mr-1"></i>Video Sample File Duration
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="ffmpeg_duration" name="ffmpeg_duration" value="{{ $site['ffmpeg_duration'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">seconds</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum duration (in seconds) for ffmpeg to generate the sample for. (Default 5)</p>
                        </div>

                        <div>
                            <label for="maxnestedlevels" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-layer-group mr-1"></i>Nested Archive Depth
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxnestedlevels" name="maxnestedlevels" value="{{ $site['maxnestedlevels'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">levels</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">If a rar/zip has rar/zip inside of it, how many times should we go in those inner rar/zip files.</p>
                        </div>

                        <div>
                            <label for="innerfileblacklist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-ban mr-1"></i>Inner File Black List Regex
                            </label>
                            <textarea id="innerfileblacklist" name="innerfileblacklist" rows="3" placeholder="Example: /setup\.exe|password\.url/i"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['innerfileblacklist'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex. <strong>You must ensure this regex is valid, a non valid regex will cause errors during processing!</strong></p>
                        </div>
                    </div>
                </div>
