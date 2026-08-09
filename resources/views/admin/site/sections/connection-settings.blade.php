                <!-- Connection Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Connection Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="nntpretries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-refresh mr-1"></i>NNTP Retry Attempts
                            </label>
                            <input type="text" id="nntpretries" name="nntpretries" value="{{ $site['nntpretries'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of retry attempts to connect to nntp provider. On error, each retry takes approximately 5 seconds nntp returns reply. (Default 10)</p>
                        </div>

                        <div>
                            <label for="delaytime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-clock-o mr-1"></i>Delay Time Check
                            </label>
                            <input type="text" id="delaytime" name="delaytime" value="{{ $site['delaytime'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The time in hours to wait, since last activity, before releases without parts counts in the subject are are created.<br>Setting this below 2 hours could create incomplete releases.</p>
                        </div>

                        <div>
                            <label for="collection_timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-hourglass-end mr-1"></i>Collection Timeout Check
                            </label>
                            <input type="text" id="collection_timeout" name="collection_timeout" value="{{ $site['collection_timeout'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">How many hours to wait before converting a collection into a release that is considered "stuck".<br>Default value is 48 hours.</p>
                        </div>
                    </div>
                </div>
