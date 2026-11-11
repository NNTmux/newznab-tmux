@extends('layouts.main')

@section('content')
@php
    $seriesCount = collect($serieslist)->sum(fn ($group) => count($group));
    $letters = array_merge(['0-9'], $seriesrange);
    $returnTo = urlencode(request()->fullUrl());
@endphp
<x-page-header title="TV series" eyebrow="Discover and follow" description="Browse available series, find a title, and manage the shows you follow." icon="fas fa-tv">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($seriesCount) }} {{ Str::plural('series', $seriesCount) }}</span>
        @if(($showname ?? '') !== '')
            <span class="workspace-hero__stat"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>“{{ $showname }}”</span>
        @elseif($seriesletter !== '')
            <span class="workspace-hero__stat"><i class="fas fa-font" aria-hidden="true"></i>{{ $seriesletter }}</span>
        @endif
    </x-slot:stats>
    <x-slot:actions>
        <a href="{{ route('trending-tv') }}" class="workspace-hero__action"><i class="fas fa-fire" aria-hidden="true"></i>Trending shows</a>
        <a href="{{ route('myshows') }}" class="workspace-hero__action"><i class="fas fa-bookmark" aria-hidden="true"></i>My shows</a>
        <a href="{{ route('myshows.browse') }}" class="workspace-hero__action"><i class="fas fa-list" aria-hidden="true"></i>My show releases</a>
    </x-slot:actions>
</x-page-header>

<div class="surface-panel overflow-hidden rounded-xl shadow-sm">
    {{-- Letter navigation and search --}}
    <div class="flex flex-col gap-3 border-b border-line px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <nav aria-label="Jump to letter" class="flex flex-wrap gap-1">
            @foreach($letters as $letter)
                <a href="{{ url('/series/'.$letter) }}"
                   @if($letter === $seriesletter) aria-current="page" @endif
                   @class([
                       'inline-flex h-8 min-w-8 items-center justify-center rounded-control px-2 text-sm font-semibold transition',
                       'bg-accent text-on-accent' => $letter === $seriesletter,
                       'text-fg-muted hover:bg-surface-hover hover:text-fg' => $letter !== $seriesletter,
                   ])>{{ $letter }}</a>
            @endforeach
        </nav>

        <form method="get" action="{{ url('/series') }}" role="search" class="flex w-full gap-2 lg:w-72 lg:shrink-0">
            <x-input name="title" :value="$showname ?? ''" placeholder="Search series" aria-label="Search series" class="min-h-9 py-1.5" />
            <x-icon-button type="submit" variant="primary" size="sm" icon="fa-solid fa-magnifying-glass" label="Search series" />
        </form>
    </div>

    @forelse($serieslist as $sletter => $series)
        <section aria-labelledby="series-group-{{ $loop->index }}">
            <h2 id="series-group-{{ $loop->index }}" class="flex items-center justify-between border-b border-line bg-surface-sunken px-4 py-1.5 text-sm font-bold text-fg sm:px-6">
                {{ $sletter }}
                <span class="text-xs font-medium text-fg-muted tabular-nums">{{ count($series) }}</span>
            </h2>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($series as $s)
                    @php
                        $sData = is_object($s) ? get_object_vars($s) : $s;
                        $externalLinks = array_filter([
                            'TVDB' => ! empty($sData['tvdb']) && $sData['tvdb'] > 0 ? 'https://thetvdb.com/?tab=series&id='.$sData['tvdb'] : null,
                            'TVMaze' => ! empty($sData['tvmaze']) && $sData['tvmaze'] > 0 ? 'https://www.tvmaze.com/shows/'.$sData['tvmaze'] : null,
                            'Trakt' => ! empty($sData['trakt']) && $sData['trakt'] > 0 ? 'https://trakt.tv/shows/'.$sData['trakt'] : null,
                        ]);
                    @endphp
                    <li class="flex flex-col gap-2 px-4 py-2.5 transition hover:bg-surface-hover sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <a class="font-semibold text-link hover:underline" title="View series details" href="{{ url('/series/'.$sData['id']) }}">{{ $sData['title'] ?? '' }}</a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-fg-muted">
                                @if(! empty($sData['publisher']))
                                    <span><i aria-hidden="true" class="fa-solid fa-tower-broadcast mr-1"></i>{{ $sData['publisher'] }}</span>
                                @endif
                                @if(! empty($sData['countries_id']))
                                    <span><i aria-hidden="true" class="fa-solid fa-globe mr-1"></i>{{ $sData['countries_id'] }}</span>
                                @endif
                                @if(! empty($sData['prevdate']))
                                    <span><i aria-hidden="true" class="fa-solid fa-calendar mr-1"></i>Last: {{ $sData['previnfo'] ?? '' }} · {{ \Carbon\Carbon::parse($sData['prevdate'])->format('M d, Y') }}</span>
                                @endif
                            </div>
                        </div>

                        @if($externalLinks !== [] && $sData['id'] > 0)
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach($externalLinks as $provider => $link)
                                    <a href="{{ $site->dereferrerLink }}{{ $link }}" target="_blank" rel="noopener noreferrer" title="View at {{ $provider }}"
                                       class="rounded-md border border-line px-2 py-0.5 text-xs font-medium text-fg-muted transition hover:bg-surface-hover hover:text-fg">{{ $provider }}</a>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-1 sm:w-40 sm:justify-end">
                            @if(! empty($sData['userseriesid']))
                                <x-badge tone="success" icon="fa-solid fa-check">Following</x-badge>
                                <x-icon-button size="sm" icon="fa-solid fa-pen" :label="'Edit categories for '.($sData['title'] ?? 'this show')"
                                               :href="url('/myshows?action=edit&id='.$sData['id'].'&from='.$returnTo)" />
                                <form method="POST" action="{{ url('/myshows?action=delete&id='.$sData['id'].'&from='.$returnTo) }}"
                                      x-data="confirmForm" @submit.prevent="submit()" data-title="Remove show" data-message="Stop following this show?" data-confirm-text="Remove" data-type="danger">
                                    @csrf
                                    <x-icon-button type="submit" size="sm" icon="fa-solid fa-trash" :label="'Remove '.($sData['title'] ?? 'this show').' from My shows'" class="text-danger-600 dark:text-danger-400" />
                                </form>
                            @else
                                <x-button size="sm" variant="soft" icon="fa-solid fa-plus" title="Add to My shows"
                                          :href="url('/myshows?action=add&id='.$sData['id'].'&from='.$returnTo)">Follow</x-button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-empty-state icon="fas fa-tv" title="No series found" message="Try a different search or letter." />
    @endforelse
</div>
@endsection
