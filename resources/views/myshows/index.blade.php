@php
    $rssFeedUrl = url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}");
    $showCount = count($shows ?? []);
    $noCover = url('/covers/tvshows/no-cover.jpg');
@endphp
<x-page-header title="My TV shows" eyebrow="Your watchlist" description="Manage followed series and keep automatic updates flowing through your personal feed." icon="fas fa-tv">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-bookmark" aria-hidden="true"></i>{{ number_format($showCount) }} followed</span>
    </x-slot:stats>
    <x-slot:actions>
        <a href="{{ route('series') }}" class="workspace-hero__action"><i class="fas fa-tv" aria-hidden="true"></i>Browse series</a>
        <a href="{{ route('myshows.browse') }}" class="workspace-hero__action"><i class="fas fa-list" aria-hidden="true"></i>My show releases</a>
        <a href="{{ $rssFeedUrl }}" class="workspace-hero__action"><i class="fas fa-rss" aria-hidden="true"></i>RSS feed</a>
    </x-slot:actions>
</x-page-header>

<div class="surface-panel overflow-hidden rounded-xl shadow-sm">
    <div class="flex flex-col gap-1 border-b border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <h2 class="flex items-center gap-2 text-base font-semibold text-fg">
            <i aria-hidden="true" class="fa-solid fa-bookmark text-link"></i>Your shows
            <span class="text-sm font-medium text-fg-muted tabular-nums">{{ $showCount }}</span>
        </h2>
        <p class="text-xs text-fg-muted">
            <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>New episodes are added to your
            <a href="{{ $rssFeedUrl }}" class="font-medium text-link hover:underline">personal RSS feed</a>.
        </p>
    </div>

    @if($showCount > 0)
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($shows as $show)
                @php
                    $showTitle = (string) ($show['title'] ?? '');
                    $meta = array_filter([
                        $show['publisher'] ?? null,
                        ! empty($show['started']) ? 'Since '.\Carbon\Carbon::parse($show['started'])->format('Y') : null,
                    ]);
                    $coverUrl = ! empty($show['image']) ? getImageAssetUrl('tvshows', (string) $show['videos_id'], $noCover) : $noCover;
                @endphp
                <li class="flex flex-col gap-3 px-4 py-3 transition hover:bg-surface-hover sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <img width="48" height="72" class="aspect-[2/3] w-12 shrink-0 rounded-md object-cover shadow-sm"
                             src="{{ $coverUrl }}" data-fallback-src="{{ $noCover }}" loading="lazy" decoding="async" alt="{{ $showTitle }} cover"/>
                        <div class="min-w-0">
                            <a class="font-semibold text-link hover:underline" title="View series details" href="{{ url("/series/{$show['videos_id']}") }}">{{ $showTitle }}</a>
                            @if($meta !== [])
                                <p class="truncate text-xs text-fg-muted" title="{{ implode(' · ', $meta) }}">{{ implode(' · ', $meta) }}</p>
                            @endif
                            @if(! empty($show['summary']))
                                <p class="mt-1 hidden text-sm text-fg-muted lg:line-clamp-2">{{ $show['summary'] }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:w-64 sm:flex-col sm:items-start sm:gap-1">
                        <x-badge :tone="! empty($show['categoryNames']) ? 'primary' : 'neutral'" icon="fa-solid fa-folder-open">
                            {{ ! empty($show['categoryNames']) ? $show['categoryNames'] : 'All categories' }}
                        </x-badge>
                        @if(isset($show['created_at']))
                            <span class="text-xs text-fg-muted"><i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Added {{ userDate($show['created_at'], 'M d, Y') }}</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1 sm:justify-end">
                        <x-icon-button size="sm" icon="fa-solid fa-pen" :label="'Edit categories for '.($show['title'] ?? 'this show')"
                                       :href="url('/myshows?action=edit&id='.$show['videos_id'])" />
                        <form method="POST" action="{{ url("/myshows?action=delete&id={$show['videos_id']}") }}"
                              x-data="confirmForm" @submit.prevent="submit()" data-title="Remove show" data-message="Are you sure you want to remove this show from your list?" data-confirm-text="Remove" data-type="danger">
                            @csrf
                            <x-icon-button type="submit" size="sm" icon="fa-solid fa-trash" :label="'Remove '.($show['title'] ?? 'this show').' from My shows'" class="text-danger-600 dark:text-danger-400" />
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="fas fa-tv" title="No TV shows yet"
                       message="You haven't followed any series yet. Browse the series list and follow the ones you want new episodes for."
                       :action-url="route('series')" action-label="Browse series" action-icon="fas fa-tv" />
    @endif
</div>
