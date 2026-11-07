{{--
    Rotten Tomatoes Tomatometer from OMDb ("78%"). Renders nothing for "N/A" or empty values.
    60% and above is "Fresh" (red tomato), below is "Rotten" (green splat).
    size="chip" renders a compact pill for movie cards; the default is the large detail-page badge.
--}}
@props([
    'score' => null,
    'size' => 'lg',
])

@php
    $percent = preg_match('/^(\d{1,3})%$/', trim((string) $score), $match) === 1 ? min(100, (int) $match[1]) : null;
    $fresh = $percent !== null && $percent >= 60;
@endphp

@if($percent !== null && $size === 'chip')
    <span {{ $attributes->merge(['class' => 'movie-meta-chip '.($fresh ? 'movie-meta-chip--rt-fresh' : 'movie-meta-chip--rt-rotten')]) }}
          title="Rotten Tomatoes: {{ $percent }}% ({{ $fresh ? 'Fresh' : 'Rotten' }})">
        <i aria-hidden="true" class="fas {{ $fresh ? 'fa-apple-whole' : 'fa-splotch' }}"></i>{{ $percent }}%
        <span class="sr-only">Rotten Tomatoes, {{ $fresh ? 'Fresh' : 'Rotten' }}</span>
    </span>
@elseif($percent !== null)
    <div {{ $attributes->merge(['class' => 'movie-rt-score flex items-center gap-2']) }} title="Rotten Tomatoes Tomatometer">
        <span @class([
            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg',
            'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400' => $fresh,
            'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-400' => ! $fresh,
        ]) aria-hidden="true">
            <i aria-hidden="true" class="fas {{ $fresh ? 'fa-apple-whole' : 'fa-splotch' }}"></i>
        </span>
        <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $percent }}%</span>
        <span class="text-sm text-gray-600 dark:text-gray-400">Rotten Tomatoes · {{ $fresh ? 'Fresh' : 'Rotten' }}</span>
    </div>
@endif
