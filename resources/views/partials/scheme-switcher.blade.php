{{-- Shared color scheme switcher. JS hooks by button class (see alpine/stores/theme.js). Expects: $switcherId, $btnClass, $mobile (bool) --}}
@php $currentScheme = $userColorScheme ?? 'blue'; @endphp
<div class="flex items-center justify-center {{ $mobile ? 'gap-3' : 'gap-2' }}" id="{{ $switcherId }}">
    <button type="button" data-scheme="blue" title="Blue"
        class="{{ $btnClass }} {{ $mobile ? 'w-9 h-9' : 'w-8 h-8' }} rounded-full bg-blue-600 transition {{ $mobile ? 'touch-target ' : '' }}ring-offset-2 ring-offset-gray-900 {{ $currentScheme === 'blue' ? 'ring-2 ring-primary-500' : '' }}">
        <span class="sr-only">Blue</span>
    </button>
    <button type="button" data-scheme="emerald" title="Emerald"
        class="{{ $btnClass }} {{ $mobile ? 'w-9 h-9' : 'w-8 h-8' }} rounded-full bg-emerald-600 transition {{ $mobile ? 'touch-target ' : '' }}ring-offset-2 ring-offset-gray-900 {{ $currentScheme === 'emerald' ? 'ring-2 ring-primary-500' : '' }}">
        <span class="sr-only">Emerald</span>
    </button>
    <button type="button" data-scheme="violet" title="Violet"
        class="{{ $btnClass }} {{ $mobile ? 'w-9 h-9' : 'w-8 h-8' }} rounded-full bg-violet-600 transition {{ $mobile ? 'touch-target ' : '' }}ring-offset-2 ring-offset-gray-900 {{ $currentScheme === 'violet' ? 'ring-2 ring-primary-500' : '' }}">
        <span class="sr-only">Violet</span>
    </button>
</div>
