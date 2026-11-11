@php
    $rssFeedUrl = url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}");
    $movieCount = count($movies ?? []);
    $noCover = url('/covers/movies/no-cover.jpg');
@endphp
<x-page-header title="My movies" eyebrow="Your watchlist" description="Manage saved movies and receive automatic updates when releases become available." icon="fas fa-film">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-bookmark" aria-hidden="true"></i>{{ number_format($movieCount) }} saved</span>
    </x-slot:stats>
    <x-slot:actions>
        <a href="{{ url('/Movies') }}" class="workspace-hero__action"><i class="fas fa-film" aria-hidden="true"></i>Browse movies</a>
        <a href="{{ $rssFeedUrl }}" class="workspace-hero__action"><i class="fas fa-rss" aria-hidden="true"></i>RSS feed</a>
    </x-slot:actions>
</x-page-header>

<div class="surface-panel overflow-hidden rounded-xl shadow-sm">
    <div class="flex flex-col gap-1 border-b border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <h2 class="flex items-center gap-2 text-base font-semibold text-fg">
            <i aria-hidden="true" class="fa-solid fa-bookmark text-link"></i>Your movies
            <span class="text-sm font-medium text-fg-muted tabular-nums">{{ $movieCount }}</span>
        </h2>
        <p class="text-xs text-fg-muted">
            <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>Movies are added to your
            <a href="{{ $rssFeedUrl }}" class="font-medium text-link hover:underline">personal RSS feed</a> once released.
        </p>
    </div>

    @if($movieCount > 0)
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($movies as $movie)
                @php
                    $movieTitle = (string) ($movie['title'] ?? '');
                    $meta = array_filter([$movie['genre'] ?? null, ! empty($movie['director']) ? 'Directed by '.$movie['director'] : null]);
                @endphp
                <li class="flex flex-col gap-3 px-4 py-3 transition hover:bg-surface-hover sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <img width="48" height="72" class="aspect-[2/3] w-12 shrink-0 rounded-md object-cover shadow-sm"
                             src="{{ ($movie['cover'] ?? 0) == 1 ? getImageAssetUrl('movies', $movie['imdbid'].'-cover', $noCover) : $noCover }}"
                             data-fallback-src="{{ $noCover }}" loading="lazy" decoding="async" alt="{{ $movieTitle }} cover"/>
                        <div class="min-w-0">
                            <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="font-semibold text-link hover:underline">
                                {{ $movieTitle }}@if(! empty($movie['year'])) <span class="font-normal text-fg-muted">({{ $movie['year'] }})</span>@endif
                            </a>
                            @if($meta !== [])
                                <p class="truncate text-xs text-fg-muted" title="{{ implode(' · ', $meta) }}">{{ implode(' · ', $meta) }}</p>
                            @endif
                            @if(! empty($movie['plot']))
                                <p class="mt-1 hidden text-sm text-fg-muted lg:line-clamp-2">{{ $movie['plot'] }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:w-64 sm:flex-col sm:items-start sm:gap-1">
                        <x-badge :tone="! empty($movie['categoryNames']) ? 'primary' : 'neutral'" icon="fa-solid fa-folder-open">
                            {{ ! empty($movie['categoryNames']) ? $movie['categoryNames'] : 'All categories' }}
                        </x-badge>
                        @if(isset($movie['created_at']))
                            <span class="text-xs text-fg-muted"><i aria-hidden="true" class="fa-solid fa-clock mr-1"></i>Added {{ userDate($movie['created_at'], 'M d, Y') }}</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1 sm:justify-end">
                        <a href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $movie['imdbid'] }}" target="_blank" rel="noopener noreferrer" title="View on IMDb"
                           class="mr-1 inline-flex min-h-9 items-center gap-1.5 rounded-control border border-line px-3 text-xs font-medium text-fg-muted transition hover:bg-surface-hover hover:text-fg">
                            IMDb<i aria-hidden="true" class="fa-solid fa-arrow-up-right-from-square text-[0.65rem]"></i>
                        </a>
                        <x-icon-button size="sm" icon="fa-solid fa-pen" :label="'Edit categories for '.($movieTitle ?: 'this movie')"
                                       :href="url('/mymovies?id=edit&imdb='.$movie['imdbid'])" />
                        <form method="POST" action="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}"
                              x-data="confirmForm" @submit.prevent="submit()" data-title="Remove movie" data-message="Are you sure you want to remove this movie from your watchlist?" data-confirm-text="Remove" data-type="danger">
                            @csrf
                            <x-icon-button type="submit" size="sm" icon="fa-solid fa-trash" :label="'Remove '.($movieTitle ?: 'this movie').' from My movies'" class="text-danger-600 dark:text-danger-400" />
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="fas fa-film" title="No movies yet"
                       message="You haven't saved any movies yet. Open a movie and add it to your watchlist to get its releases."
                       :action-url="url('/Movies')" action-label="Browse movies" action-icon="fas fa-film" />
    @endif
</div>
