            {{-- Similar releases (same main category, similar name) --}}
            @php
                $similarReleases = collect($similars ?? [])->take(8);
            @endphp
            @if($similarReleases->isNotEmpty())
                <section class="similar-releases surface-panel-alt rounded-lg border p-6" aria-labelledby="similar-releases-heading">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 id="similar-releases-heading" class="flex items-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                            <i aria-hidden="true" class="fas fa-clone mr-2 text-link dark:text-primary-400"></i> Similar releases
                        </h3>
                        @if(count($similars ?? []) > $similarReleases->count())
                            <a href="{{ route('search', ['search' => $searchname ?? '']) }}" class="text-sm text-link hover:underline dark:text-primary-400">
                                See all {{ count($similars) }}
                            </a>
                        @endif
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($similarReleases as $similar)
                            <li class="flex items-center gap-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ url('/details/'.$similar->guid) }}" class="block truncate text-sm font-medium text-link hover:underline dark:text-primary-400" title="{{ $similar->searchname }}">{{ $similar->searchname }}</a>
                                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-600 tabular-nums dark:text-gray-400">
                                        @if(!empty($similar->category_name))
                                            <span>{{ $similar->category_name }}</span>
                                        @endif
                                        <span>{{ formatBytes($similar->size ?? 0) }}</span>
                                        @if(!empty($similar->postdate))
                                            <span>{{ userDateDiffForHumans($similar->postdate) }}</span>
                                        @endif
                                        <span>{{ number_format((int) ($similar->grabs ?? 0)) }} {{ Str::plural('grab', (int) ($similar->grabs ?? 0)) }}</span>
                                    </p>
                                </div>
                                <x-icon-button :href="url('/getnzb/'.$similar->guid)" icon="fas fa-download" :label="'Download '.$similar->searchname" variant="soft" size="sm" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
