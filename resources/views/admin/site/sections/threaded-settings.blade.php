                <!-- Advanced - Threaded Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced - Threaded Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="binarythreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>Update Binaries Threads
                            </label>
                            <input type="text" id="binarythreads" name="binarythreads" value="{{ $site['binarythreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for update_binaries. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads. At least until the cause can be determined.</p>
                        </div>

                        <div>
                            <label for="backfillthreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>Backfill Threads
                            </label>
                            <input type="text" id="backfillthreads" name="backfillthreads" value="{{ $site['backfillthreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for backfill.</p>
                        </div>

                        <div>
                            <label for="releasethreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>Update Releases Threads
                            </label>
                            <input type="text" id="releasethreads" name="releasethreads" value="{{ $site['releasethreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for releases update scripts.</p>
                        </div>

                        <div>
                            <label for="postthreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>Postprocessing Additional Threads
                            </label>
                            <input type="text" id="postthreads" name="postthreads" value="{{ $site['postthreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Maximum simultaneous additional-processing workers and NNTP sessions. Workers reuse their process and connection across bounded batches; raise this only within CPU, memory, disk I/O, and provider connection limits.</p>
                        </div>

                        <div>
                            <label for="nfothreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>NFO Threads
                            </label>
                            <input type="text" id="nfothreads" name="nfothreads" value="{{ $site['nfothreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for nfo postprocessing. The max is 16, if you set anything higher it will use 16.</p>
                        </div>

                        <div>
                            <label for="postthreadsnon" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>Postprocessing Video Metadata Threads
                            </label>
                            <input type="text" id="postthreadsnon" name="postthreadsnon" value="{{ $site['postthreadsnon'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for video metadata postprocessing. This includes movies, anime and tv lookups.</p>
                        </div>

                        <div>
                            <label for="fixnamethreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-tasks mr-1"></i>fixReleaseNames Threads
                            </label>
                            <input type="text" id="fixnamethreads" name="fixnamethreads" value="{{ $site['fixnamethreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and filenames.</p>
                        </div>
                    </div>
                </div>
