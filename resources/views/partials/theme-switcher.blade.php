{{-- Shared theme switcher. JS hooks by button class (see alpine/stores/theme.js). Expects: $switcherId, $btnClass, $mobile (bool) --}}
@php $currentTheme = $userTheme ?? 'light'; @endphp
<div class="flex items-center {{ $mobile ? 'gap-1.5' : 'gap-1' }}" id="{{ $switcherId }}">
    <button type="button" data-theme="light"
        class="{{ $btnClass }} flex-1 flex items-center justify-center gap-1.5 {{ $mobile ? 'px-3 py-2 touch-target' : 'px-2 py-1.5' }} text-xs rounded-lg transition {{ $currentTheme === 'light' ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
        title="Light Mode">
        <i class="fas fa-sun"></i>
        <span>Light</span>
    </button>
    <button type="button" data-theme="dark"
        class="{{ $btnClass }} flex-1 flex items-center justify-center gap-1.5 {{ $mobile ? 'px-3 py-2 touch-target' : 'px-2 py-1.5' }} text-xs rounded-lg transition {{ $currentTheme === 'dark' ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
        title="Dark Mode">
        <i class="fas fa-moon"></i>
        <span>Dark</span>
    </button>
    <button type="button" data-theme="system"
        class="{{ $btnClass }} flex-1 flex items-center justify-center gap-1.5 {{ $mobile ? 'px-3 py-2 touch-target' : 'px-2 py-1.5' }} text-xs rounded-lg transition {{ $currentTheme === 'system' ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}"
        title="System (Auto)">
        <i class="fas fa-desktop"></i>
        <span>Auto</span>
    </button>
</div>
