            <!-- Password Information -->
            @if(isset($release->passwordstatus) && $release->passwordstatus > 0)
                <div class="detail-password-card bg-linear-to-r from-red-50 to-orange-50 dark:from-red-900 dark:to-orange-900 rounded-lg p-6 border border-red-100 dark:border-red-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-lock mr-2 text-red-600 dark:text-red-400"></i> Password Protected Release
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Password Status</dt>
                            <dd class="mt-1">
                                @if($release->passwordstatus == 1)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <i class="fas fa-question-circle mr-1"></i> Password Unknown
                                    </span>
                                @elseif($release->passwordstatus == 2)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-check-circle mr-1"></i> Password Available
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Password</dt>
                            <dd class="mt-1">
                                @if(!empty($release->password))
                                    <div class="surface-panel rounded-lg p-3 border">
                                        <code class="text-sm text-gray-900 dark:text-gray-100 font-mono break-all">{{ $release->password }}</code>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        This password is embedded in the NZB file and will be automatically recognized by NZBGet or SABnzbd.
                                    </p>
                                @else
                                    <div class="surface-panel rounded-lg p-3 border">
                                        <code class="text-sm text-gray-500 dark:text-gray-400 font-mono">None</code>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        No password information available in the database.
                                    </p>
                                @endif
                            </dd>
                        </div>
                    </div>
                </div>
            @endif
