/**
 * Alpine.data('qualityFilter') - Resolution and source filter buttons
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
    },

    setResolution(filter) {
        this.activeResolution = filter;
        this._applyFilters();
    },

    setSource(filter) {
        this.activeSource = filter;
        this._applyFilters();
    },

    isActiveResolution(f) { return this.activeResolution === f; },
    isActiveSource(f) { return this.activeSource === f; },

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

            if (matchR && matchS) { item.style.display = ''; visible++; }
            else item.style.display = 'none';
        });
        this.visibleCount = visible;
    }
}));
