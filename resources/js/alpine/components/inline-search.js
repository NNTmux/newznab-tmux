/**
 * Inline search – plain DOM listeners (no Alpine x-data needed).
 *
 * Each .inline-search-widget has a pre-built data-base-url including category
 * param (e.g. "/search?t=6041"). This script just appends "&search=<query>".
 *
 * Supports multiple instances on the same page.
 * Works safely inside nested forms / x-data scopes.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.inline-search-widget').forEach((widget) => {
        const input = widget.querySelector('[data-role="inline-search-input"]');
        const btn = widget.querySelector('[data-role="inline-search-btn"]');
        if (!input || !btn) return;

        function doSearch() {
            const q = input.value.trim();
            if (!q) return;

            const baseUrl = widget.dataset.baseUrl || '/search';
            const sep = baseUrl.includes('?') ? '&' : '?';
            window.location.href = baseUrl + sep + 'search=' + encodeURIComponent(q);
        }

        btn.addEventListener('click', doSearch);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
    });
});

