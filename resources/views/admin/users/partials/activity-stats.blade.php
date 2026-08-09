                @if(!empty($user['id']))
                    <!-- Grabs -->
                    <div>
                        <label for="grabs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Grabs
                        </label>
                        <input type="number"
                               id="grabs"
                               name="grabs"
                               value="{{ is_array($user) ? ($user['grabs'] ?? 0) : ($user->grabs ?? 0) }}"
                               disabled
                               class="w-full px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md cursor-not-allowed opacity-75">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>Total grabs is read-only and automatically tracked
                        </p>
                    </div>

                    <!-- Daily Activity Stats -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-linear-to-br from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-chart-line mr-2 text-purple-600 dark:text-purple-400"></i>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Daily Activity (Last 24 Hours)
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Daily API Requests -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 font-medium uppercase tracking-wide">
                                            <i class="fas fa-code mr-1"></i>API Requests
                                        </p>
                                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">
                                            {{ is_array($user) ? ($user['daily_api_count'] ?? 0) : ($user->daily_api_count ?? 0) }}
                                        </p>
                                    </div>
                                    <div class="text-blue-600 dark:text-blue-400">
                                        <i class="fas fa-code text-3xl opacity-20"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                                    <i class="fas fa-clock mr-1"></i>In the last 24 hours
                                </p>
                            </div>

                            <!-- Daily Downloads -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-green-600 dark:text-green-400 font-medium uppercase tracking-wide">
                                            <i class="fas fa-download mr-1"></i>Downloads
                                        </p>
                                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">
                                            {{ is_array($user) ? ($user['daily_download_count'] ?? 0) : ($user->daily_download_count ?? 0) }}
                                        </p>
                                    </div>
                                    <div class="text-green-600 dark:text-green-400">
                                        <i class="fas fa-download text-3xl opacity-20"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-2">
                                    <i class="fas fa-clock mr-1"></i>In the last 24 hours
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400 flex items-center">
                            <i class="fas fa-info-circle mr-1.5 text-blue-500"></i>
                            <span>These counters show activity from the past 24 hours and are automatically updated.</span>
                        </p>
                    </div>
                @endif
