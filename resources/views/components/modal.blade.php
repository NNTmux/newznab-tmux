{{--
    Modal on the native <dialog> element: the browser provides the focus trap, Escape and the top
    layer, and the x-modal directive keeps the dialog in sync with Alpine state.

    `open` is the Alpine expression that is true while the modal shows. `close` is the expression
    run on Escape and on a click outside the panel. `labelledby` is the id of the heading inside.
    Extra attributes (id, x-data) land on the <dialog>; `panel-class` styles the panel.
--}}
@props([
    'open',
    'close' => null,
    'labelledby' => null,
    'size' => 'md',
    'panelClass' => 'p-6',
])

@php
    $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-2xl'];
@endphp

<dialog {{ $attributes->merge(['class' => 'app-modal confirmation-modal fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 transition-opacity dark:bg-black/70']) }}
        x-show="{{ $open }}"
        x-modal="{{ $open }}"
        @if($close) @cancel.prevent="{{ $close }}" @endif
        @if($labelledby) aria-labelledby="{{ $labelledby }}" @endif
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
    <div class="confirmation-panel mx-4 w-full rounded-2xl bg-surface text-fg shadow-overlay {{ $widths[$size] ?? $widths['md'] }} {{ $panelClass }}"
         @if($close) @click.outside="{{ $close }}" @endif>
        {{ $slot }}
    </div>
</dialog>
