{{--
    Dark mode and color scheme initialization - MUST be included at the very top of <head>,
    BEFORE any CSS/Vite tags, to prevent white flash on page load.
    1. The blocking script applies the 'dark' class, data-color-scheme, and data-loading to <html> synchronously.
    2. The style tag covers background, text color, color-scheme, x-cloak hiding, and transition
       suppression so the first paint matches the user's theme with zero flash.
    Scheme list and first-paint colors come from App\Enums\ColorScheme.
    Pass ['static' => true] on pages that must not touch auth/session/DB (error pages):
    the theme is then read from localStorage, which theme.js keeps in sync for signed-in users.
--}}
@php
    $themeInitDefaultScheme = \App\Enums\ColorScheme::default();
    $themeInitDefaultTheme = \App\Enums\ThemePreference::default()->value;
    $themeInitUser = ($static ?? false) ? null : auth()->user();
@endphp
<script nonce="{{ csp_nonce() }}">
(function() {
    var d = document.documentElement;
    d.setAttribute('data-loading', '');
    @if($themeInitUser)
        var t = @json(\App\Enums\ThemePreference::fromPreference($themeInitUser->theme_preference ?? null)->value);
        var scheme = @json(\App\Enums\ColorScheme::fromPreference($themeInitUser->color_scheme ?? null)->value);
    @else
        var t = @json($themeInitDefaultTheme);
        var scheme = @json($themeInitDefaultScheme->value);
        try {
            t = localStorage.getItem('theme') || t;
            scheme = localStorage.getItem('color_scheme') || scheme;
        } catch (_error) {
            // Browser privacy settings may disable storage.
        }
    @endif
    var allowedSchemes = @json(\App\Enums\ColorScheme::values());
    if (allowedSchemes.indexOf(scheme) === -1) {
        scheme = @json($themeInitDefaultScheme->value);
    }
    var isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (isDark) {
        d.classList.add('dark');
    }
    d.setAttribute('data-color-scheme', scheme);
    @if($adminRail ?? false)
    try {
        if (localStorage.getItem('admin_sidebar') === 'collapsed') {
            d.setAttribute('data-admin-sidebar', 'collapsed');
        }
    } catch (_error) {
        // Storage disabled: keep the full sidebar.
    }
    @endif
})();
</script>
<style nonce="{{ csp_nonce() }}">
[x-cloak] { display: none !important; }
html[data-loading], html[data-loading] *, html[data-loading] *::before, html[data-loading] *::after { transition: none !important; }
html { color-scheme: light; }
html.dark { color-scheme: dark; }
html, body { background-color: {{ $themeInitDefaultScheme->canvas(false) }}; color: {{ $themeInitDefaultScheme->foreground(false) }}; }
html.dark, html.dark body { background-color: {{ $themeInitDefaultScheme->canvas(true) }}; color: {{ $themeInitDefaultScheme->foreground(true) }}; }
@foreach(\App\Enums\ColorScheme::cases() as $themeInitScheme)
@continue($themeInitScheme === $themeInitDefaultScheme)
html[data-color-scheme="{{ $themeInitScheme->value }}"], html[data-color-scheme="{{ $themeInitScheme->value }}"] body { background-color: {{ $themeInitScheme->canvas(false) }}; color: {{ $themeInitScheme->foreground(false) }}; }
html.dark[data-color-scheme="{{ $themeInitScheme->value }}"], html.dark[data-color-scheme="{{ $themeInitScheme->value }}"] body { background-color: {{ $themeInitScheme->canvas(true) }}; color: {{ $themeInitScheme->foreground(true) }}; }
@endforeach
</style>
