                <!-- Registration Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">User Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-user-plus mr-1"></i>Registration Management
                            </label>
                            <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
                                <p class="text-sm text-primary-800 dark:text-primary-200">
                                    Registration status, scheduled open periods, and registration activity are now managed from the dedicated registration admin page.
                                </p>
                                <a href="{{ route('admin.registrations.index') }}"
                                   class="mt-3 inline-flex items-center rounded-md border border-primary-600 bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-primary-400/40 dark:bg-primary-500/90 dark:hover:bg-primary-400 dark:focus:ring-offset-gray-900">
                                    <i class="fas fa-arrow-up-right-from-square mr-2"></i>Open Registration Admin
                                </a>
                            </div>
                        </div>

                        <div>
                            <label for="userdownloadpurgedays" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-calendar mr-1"></i>User Downloads Purge Days
                            </label>
                            <input type="text" id="userdownloadpurgedays" name="userdownloadpurgedays" value="{{ $site['userdownloadpurgedays'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of days to preserve user download history, for use when checking limits being hit. Set to zero will remove all records of what users download, but retain history of when, so that role based limits can still be applied.</p>
                        </div>

                        <div>
                            <label for="userhostexclusion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-shield mr-1"></i>IP Whitelist
                            </label>
                            <input type="text" id="userhostexclusion" name="userhostexclusion" value="{{ $site['userhostexclusion'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">A comma separated list of IP addresses which will be excluded from user limits on number of requests and downloads per IP address. Include values for google reader and other shared services which may be being used.</p>
                        </div>
                    </div>
                </div>
