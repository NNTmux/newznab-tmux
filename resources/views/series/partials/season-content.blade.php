<div class="p-4" data-series-season-content>
    @forelse($seasons as $seasonNumber => $episodes)
        <div class="season-content" data-season="{{ $seasonNumber }}">
            @foreach($episodes as $episodeNumber => $releases)
                <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Episode {{ $episodeNumber }}
                    </h4>
                    <div class="space-y-2">
                        @foreach($releases as $release)
                            <div class="series-episode-card flex items-center gap-3 bg-gray-50 dark:bg-gray-900 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <div class="shrink-0">
                                    <input aria-label="Select {{ $release->searchname }}" type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-link dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" name="release[]" value="{{ $release->guid }}" @change="onCheckboxChange()">
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ url('/details/' . $release->guid) }}"
                                           class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium wrap-break-word break-all">
                                            {{ $release->searchname }}
                                        </a>
                                        @if(($release->failed_count ?? 0) > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 dark:bg-danger-900/40 text-danger-800 dark:text-danger-200"
                                                  title="{{ $release->failed_count }} user(s) reported download failure">
                                                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-1"></i> Failed ({{ $release->failed_count }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-2">
                                        <span class="mr-3">
                                            <i aria-hidden="true" class="fa-regular fa-hard-drive mr-1"></i>{{ formatBytes($release->size) }}
                                        </span>
                                        <span>
                                            <i aria-hidden="true" class="fa-regular fa-clock mr-1"></i>Added: {{ userDateDiffForHumans($release->adddate) }}
                                        </span>
                                        @if(!empty($release->postdate))
                                            <span>
                                                <i aria-hidden="true" class="fas fa-calendar mr-1"></i> Posted: {{ userDate($release->postdate, 'M d, Y H:i') }}
                                            </span>
                                        @endif
                                        @if(!empty($release->fromname))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono">
                                                <i aria-hidden="true" class="fas fa-user mr-1"></i>{{ $release->fromname }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <x-release-row-actions :result="$release" :compact="true" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <x-empty-state icon="fas fa-tv" title="No releases for this season" message="Nothing has been posted on this page for the selected season." />
    @endforelse
</div>
