@props([
    'release',
    'index' => 0,
])

@if(($release->searchname ?? null) && ($release->guid ?? null))
    <article class="movie-release-row surface-panel-alt rounded-xl border p-3">
        <div class="release-card-container">
            <div class="release-info-wrapper min-w-0 flex-1">
                <a href="{{ url('/details/' . $release->guid) }}"
                   class="line-clamp-2 block break-all text-sm font-semibold leading-5 text-gray-800 hover:text-primary-600 dark:text-gray-200 dark:hover:text-primary-400"
                   title="{{ $release->searchname }}">
                    {{ $release->searchname }}
                </a>

                <x-release-badges :result="$release" />

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    @if($release->size)
                        <span class="release-chip py-1">
                            <i class="fas fa-hard-drive mr-1" aria-hidden="true"></i>{{ number_format($release->size / 1073741824, 2) }} GB
                        </span>
                    @endif

                    @if($release->postdate)
                        <span class="release-chip py-1">
                            <i class="fas fa-calendar-days mr-1" aria-hidden="true"></i>{{ userDate($release->postdate, 'M j, Y') }}
                        </span>
                    @endif

                    @if($release->adddate)
                        <span class="release-chip py-1">
                            <i class="fas fa-clock mr-1" aria-hidden="true"></i>{{ userDateDiffForHumans($release->adddate) }}
                        </span>
                    @endif

                </div>
            </div>

            <div class="release-actions">
                <x-release-row-actions :result="$release" />
            </div>
        </div>
    </article>
@endif
