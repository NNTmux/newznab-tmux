{{--
    Dark mode and color scheme initialization - MUST be included at the very top of <head>,
    BEFORE any CSS/Vite tags, to prevent white flash on page load.
    1. The blocking script applies the 'dark' class, data-color-scheme, and data-loading to <html> synchronously.
    2. The style tag covers background, text color, color-scheme, x-cloak hiding, and transition
       suppression so the first paint matches the user's theme with zero flash.
--}}
<script nonce="{{ csp_nonce() }}">
(function() {
    var d = document.documentElement;
    d.setAttribute('data-loading', '');
    @auth
        var t = '{{ auth()->user()->theme_preference ?? "light" }}';
        var scheme = '{{ auth()->user()->color_scheme ?? "blue" }}';
    @else
        var t = localStorage.getItem('theme') || 'light';
        var scheme = localStorage.getItem('color_scheme') || 'blue';
    @endauth
    var isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (isDark) {
        d.classList.add('dark');
    }
    d.setAttribute('data-color-scheme', scheme);
})();
</script>
<style nonce="{{ csp_nonce() }}">
[x-cloak] { display: none !important; }
html[data-loading], html[data-loading] *, html[data-loading] *::before, html[data-loading] *::after { transition: none !important; }
html { color-scheme: light; }
html.dark { color-scheme: dark; }
html, body { background-color: #f8fafc; color: #1e293b; }
html.dark, html.dark body { background-color: #0f172a; color: #e2e8f0; }
html[data-color-scheme="emerald"], html[data-color-scheme="emerald"] body { background-color: #f0fdf4; color: #1e293b; }
html.dark[data-color-scheme="emerald"], html.dark[data-color-scheme="emerald"] body { background-color: #071a12; color: #d1fae5; }
html[data-color-scheme="violet"], html[data-color-scheme="violet"] body { background-color: #faf5ff; color: #1e293b; }
html.dark[data-color-scheme="violet"], html.dark[data-color-scheme="violet"] body { background-color: #120b20; color: #e9d5ff; }
</style>
