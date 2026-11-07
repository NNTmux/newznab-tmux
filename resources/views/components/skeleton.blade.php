{{-- Loading placeholder lines. The shimmer stops for users who prefer reduced motion. --}}
@props([
    'lines' => 3,
])

<div role="status" {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <span class="sr-only">{{ __('Loading…') }}</span>
    @for($line = 0; $line < $lines; $line++)
        <div aria-hidden="true" @class(['skeleton h-4 rounded-full', 'w-full' => $line % 3 === 0, 'w-11/12' => $line % 3 === 1, 'w-2/3' => $line % 3 === 2])></div>
    @endfor
</div>
