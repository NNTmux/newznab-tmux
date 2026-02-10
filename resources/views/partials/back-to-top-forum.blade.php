{{-- Back to top button for forum (Vue layout - no Alpine) --}}
<button type="button" id="back-to-top-forum" class="hidden fixed bottom-6 right-6 z-40 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 transition-colors" aria-label="Back to top">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
</button>
<script>
(function() {
    const btn = document.getElementById('back-to-top-forum');
    if (!btn) return;
    const threshold = 300;
    function toggle() { btn.classList.toggle('hidden', window.scrollY <= threshold); }
    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
    btn.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
})();
</script>
