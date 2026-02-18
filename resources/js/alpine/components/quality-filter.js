/**
 * Alpine.data('qualityFilter') - Resolution and source filter buttons
 * CSP-safe: Uses click handler with data attributes instead of inline expressions
 */
import Alpine from '@alpinejs/csp';

Alpine.data('qualityFilter', () => ({
    activeResolution: 'all',
    activeSource: 'all',
    totalReleases: 0,
    visibleCount: 0,

    init() {
        this.totalReleases = this.$el.querySelectorAll('.release-item').length;
        this.visibleCount = this.totalReleases;

        // Set up click handlers via event delegation
        this.$el.addEventListener('click', (e) => {
            const resBtn = e.target.closest('[data-resolution]');
            if (resBtn) {
                const filter = resBtn.getAttribute('data-resolution');
                if (filter) {
                    this.activeResolution = filter;
                    this._updateButtonStyles();
                    this._applyFilters();
                }
                return;
            }

            const srcBtn = e.target.closest('[data-source]');
            if (srcBtn) {
                const filter = srcBtn.getAttribute('data-source');
                if (filter) {
                    this.activeSource = filter;
                    this._updateButtonStyles();
                    this._applyFilters();
                }
            }
        });

        // Initial button styles
        this._updateButtonStyles();
    },

    /**
     * Update button active/inactive styles
     */
    _updateButtonStyles() {
        // Resolution buttons
        this.$el.querySelectorAll('[data-resolution]').forEach(btn => {
            const filter = btn.getAttribute('data-resolution');
            const isActive = filter === this.activeResolution;

            // Remove all state classes first
            btn.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
            btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');

            if (isActive) {
                btn.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
            } else {
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');
            }
        });

        // Source buttons
        this.$el.querySelectorAll('[data-source]').forEach(btn => {
            const filter = btn.getAttribute('data-source');
            const isActive = filter === this.activeSource;

            // Remove all state classes first
            btn.classList.remove('bg-purple-600', 'text-white', 'hover:bg-purple-700');
            btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');

            if (isActive) {
                btn.classList.add('bg-purple-600', 'text-white', 'hover:bg-purple-700');
            } else {
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-300', 'dark:hover:bg-gray-600');
            }
        });
    },

    countText() {
        if (this.activeResolution === 'all' && this.activeSource === 'all') return '(' + this.totalReleases + ' total)';
        return '(' + this.visibleCount + ' of ' + this.totalReleases + ')';
    },

    _applyFilters() {
        let visible = 0;
        this.$el.querySelectorAll('.release-item').forEach(item => {
            const name = (item.getAttribute('data-release-name') || '').toLowerCase();
            let matchR = true, matchS = true;

            if (this.activeResolution !== 'all') matchR = name.includes(this.activeResolution.toLowerCase());
            if (this.activeSource !== 'all') {
                const s = this.activeSource.toLowerCase();
                if (s === 'bluray') matchS = name.includes('bluray') || name.includes('blu-ray') || name.includes('bdrip') || name.includes('brrip');
                else if (s === 'web-dl') matchS = name.includes('web-dl') || name.includes('webdl') || name.includes('web.dl');
                else if (s === 'webrip') matchS = name.includes('webrip') || name.includes('web-rip') || name.includes('web.rip');
                else matchS = name.includes(s);
            }

            if (matchR && matchS) {
                item.style.removeProperty('display');
                item.classList.remove('hidden');
                visible++;
            } else {
                item.style.setProperty('display', 'none', 'important');
            }
        });
        this.visibleCount = visible;
    }
}));
