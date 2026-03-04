{{--
    Dark mode and color scheme initialization - MUST be included at the very top of <head>,
    BEFORE any CSS/Vite tags, to prevent white flash on page load.
    1. The blocking script applies the 'dark' class and data-color-scheme to <html> synchronously.
    2. The style tag that follows uses those attributes to set html AND body background-color
       for every scheme/dark combo so the first paint is never white.
--}}
<script nonce="{{ csp_nonce() }}">
(function() {
    var d = document.documentElement;
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
html, body { background-color: #f8fafc; }
html.dark, html.dark body { background-color: #0f172a; }
html[data-color-scheme="emerald"], html[data-color-scheme="emerald"] body { background-color: #f0fdf4; }
html.dark[data-color-scheme="emerald"], html.dark[data-color-scheme="emerald"] body { background-color: #071a12; }
html[data-color-scheme="violet"], html[data-color-scheme="violet"] body { background-color: #faf5ff; }
html.dark[data-color-scheme="violet"], html.dark[data-color-scheme="violet"] body { background-color: #120b20; }
</style>
