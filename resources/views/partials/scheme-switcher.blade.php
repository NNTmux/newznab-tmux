{{-- Shared color scheme switcher. JS hooks by button class (see alpine/stores/theme.js). Expects: $switcherId, $btnClass, $mobile (bool) --}}
@php
    $currentScheme = \App\Enums\ColorScheme::fromPreference($userColorScheme ?? null);
@endphp
<div class="grid grid-cols-4 place-items-center {{ $mobile ? 'gap-3' : 'gap-2' }}" id="{{ $switcherId }}">
    @foreach(\App\Enums\ColorScheme::cases() as $scheme)
        <button type="button"
            data-scheme="{{ $scheme->value }}"
            title="{{ $scheme->label() }}"
            aria-pressed="{{ $currentScheme === $scheme ? 'true' : 'false' }}"
            class="{{ $btnClass }} {{ $mobile ? 'w-9 h-9' : 'w-8 h-8' }} rounded-full {{ $scheme->swatchClass() }} transition {{ $mobile ? 'touch-target ' : '' }}ring-offset-2 ring-offset-gray-900 {{ $currentScheme === $scheme ? 'ring-2 ring-primary-500' : '' }}">
            <span class="sr-only">{{ $scheme->label() }}</span>
        </button>
    @endforeach
</div>
