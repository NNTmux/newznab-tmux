                <!-- Additional Usenet Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Additional Usenet Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="maxsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-archive mr-1"></i>Maximum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxsizetopostprocess" name="maxsizetopostprocess" value="{{ $site['maxsizetopostprocess'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">GB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum size in gigabytes to postprocess a release. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="minsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-archive mr-1"></i>Minimum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="minsizetopostprocess" name="minsizetopostprocess" value="{{ $site['minsizetopostprocess'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">MB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The minimum size in megabytes to post process (additional) a release. If set to 0, then ignored.</p>
                        </div>
                    </div>
                </div>
