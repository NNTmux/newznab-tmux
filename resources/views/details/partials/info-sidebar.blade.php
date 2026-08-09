        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="detail-info-sidebar surface-panel-alt rounded-lg p-4 sticky top-4 border">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->category_name ?? 'Other' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Size</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($release->size / 1073741824, 2) }} GB</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Files</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->totalpart ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Added</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ userDate($release->adddate, 'M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Group</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->group_name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ userDate($release->postdate, 'M d, Y H:i') }}</dd>
                    </div>
                    @if(!empty($release->fromname))
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Posted By</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 break-all">
                                <span class="inline-flex items-center px-2 py-1 rounded bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200 text-xs font-mono">
                                    <i class="fas fa-user mr-1"></i>{{ $release->fromname }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Grabs</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $release->grabs ?? 0 }}</dd>
                    </div>
                    @if($release->imdbid ?? false)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IMDB</dt>
                            <dd class="mt-1">
                                <a href="{{ $site['dereferrer_link'] }}https://www.imdb.com/title/tt{{ $release->imdbid }}" target="_blank" class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                                    View on IMDB <i class="fas fa-external-link-alt text-xs"></i>
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
