{{--
    Dark mode and color scheme initialization - MUST be included at the very top of <head>,
    BEFORE any CSS/Vite tags, to prevent white flash on page load.
    This tiny blocking script applies the 'dark' class and data-color-scheme to <html> synchronously
    before the browser paints any content, and sets an inline background-color on <html> so the
    first paint is never white (even before external CSS loads).
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
    d.style.backgroundColor = isDark ? '#0f172a' : '#f8fafc';
})();
</script>
