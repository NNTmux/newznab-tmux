{{--
    Dark mode initialization - MUST be included at the very top of <head>,
    BEFORE any CSS/Vite tags, to prevent white flash on page load.
    This tiny blocking script applies the 'dark' class to <html> synchronously
    before the browser paints any content.
--}}
<script nonce="{{ csp_nonce() }}">
(function() {
    var d = document.documentElement;
    @auth
        var t = '{{ auth()->user()->theme_preference ?? "light" }}';
    @else
        var t = localStorage.getItem('theme') || 'light';
    @endauth
    if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        d.classList.add('dark');
    }
})();
</script>
