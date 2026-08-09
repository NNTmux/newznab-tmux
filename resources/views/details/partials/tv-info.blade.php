            <!-- TV Show Information -->
            @if(!empty($show))
                @php
                    $showData = is_object($show) ? get_object_vars($show) : $show;
                    $showTitle = $showData['title'] ?? ($show->title ?? null);
                    $showStarted = $showData['started'] ?? ($show->started ?? null);
                    $showTvdb = $showData['tvdb'] ?? ($show->tvdb ?? null);
                @endphp
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-tv mr-2 text-primary-600 dark:text-primary-400"></i> TV Show Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if(!empty($showTitle))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Show Title</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $showTitle }}</dd>
                            </div>
                        @endif
                        @if(!empty($showStarted))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Started</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $showStarted }}</dd>
                            </div>
                        @endif
                        @if(!empty($showTvdb))
                            <div>
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">TVDB</dt>
                                <dd class="mt-1">
                                    <a href="{{ $site['dereferrer_link'] }}https://thetvdb.com/?tab=series&id={{ $showTvdb }}" target="_blank" class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                                        View on TVDB <i class="fas fa-external-link-alt text-xs"></i>
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
