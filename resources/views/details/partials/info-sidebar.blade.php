        <!-- Sidebar -->
        @php
            // category_name is "Parent > Child"; link it to the matching browse page.
            [$sidebarParent, $sidebarChild] = array_pad(array_map('trim', explode('>', (string) ($release->category_name ?? ''), 2)), 2, null);
            $sidebarCategoryUrl = $sidebarParent ? url('/browse/'.$sidebarParent.($sidebarChild ? '/'.$sidebarChild : '')) : null;
            $sidebarRow = 'grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-2.5';
            $sidebarLabel = 'text-sm font-medium text-gray-600 dark:text-gray-400';
            $sidebarValue = 'min-w-0 text-sm text-gray-900 dark:text-gray-100';
        @endphp
        <div class="lg:col-span-1">
            <div class="detail-info-sidebar surface-panel-alt rounded-lg p-4 sticky top-4 border">
                <h2 class="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-200">Information</h2>
                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Category</dt>
                        <dd class="{{ $sidebarValue }}">
                            @if($sidebarCategoryUrl)
                                <a href="{{ $sidebarCategoryUrl }}" class="text-link hover:underline dark:text-primary-400">{{ $release->category_name }}</a>
                            @else
                                Other
                            @endif
                        </dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Size</dt>
                        <dd class="{{ $sidebarValue }} tabular-nums" title="{{ number_format((int) ($release->size ?? 0)) }} bytes">{{ formatBytes($release->size ?? 0) }}</dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Files</dt>
                        <dd class="{{ $sidebarValue }} tabular-nums">{{ number_format((int) ($release->totalpart ?? 0)) }}</dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Grabs</dt>
                        <dd class="{{ $sidebarValue }} tabular-nums">{{ number_format((int) ($release->grabs ?? 0)) }}</dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Posted</dt>
                        <dd class="{{ $sidebarValue }}">
                            {{ userDate($release->postdate, 'M d, Y H:i') }}
                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ userDateDiffForHumans($release->postdate) }}</span>
                        </dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Added</dt>
                        <dd class="{{ $sidebarValue }}">
                            {{ userDate($release->adddate, 'M d, Y H:i') }}
                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ userDateDiffForHumans($release->adddate) }}</span>
                        </dd>
                    </div>
                    <div class="{{ $sidebarRow }}">
                        <dt class="{{ $sidebarLabel }}">Group</dt>
                        <dd class="{{ $sidebarValue }} break-all">
                            @if(!empty($release->group_name))
                                <a href="{{ route('group', ['g' => $release->group_name]) }}" class="text-link hover:underline dark:text-primary-400" title="Browse this group">{{ $release->group_name }}</a>
                            @else
                                Unknown
                            @endif
                        </dd>
                    </div>
                    @if(!empty($release->fromname))
                        <div class="{{ $sidebarRow }}">
                            <dt class="{{ $sidebarLabel }}">Posted by</dt>
                            <dd class="{{ $sidebarValue }} break-all font-mono text-xs leading-5">{{ $release->fromname }}</dd>
                        </div>
                    @endif
                    @if($release->imdbid ?? false)
                        <div class="{{ $sidebarRow }}">
                            <dt class="{{ $sidebarLabel }}">IMDb</dt>
                            <dd class="{{ $sidebarValue }}">
                                <a href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $release->imdbid }}" target="_blank" rel="noopener noreferrer" title="tt{{ $release->imdbid }}" class="text-link hover:underline dark:text-primary-400">
                                    View on IMDb <i aria-hidden="true" class="fas fa-external-link-alt text-xs"></i><span class="sr-only"> (opens in a new tab)</span>
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
