/**
 * Alpine.data('moviesLayout') - Movies page layout toggle (1/2 columns)
 */
import Alpine from '@alpinejs/csp';

Alpine.data('moviesLayout', () => ({
    layout: 2,
    mediaQuery: null,

    init() {
        const grid = document.getElementById('moviesGrid');
        if (grid) this.layout = parseInt(grid.dataset.userLayout) || 2;

        // Set up media query listener for responsive behavior
        this.mediaQuery = window.matchMedia('(min-width: 1024px)');
        this.mediaQuery.addEventListener('change', () => this._applyLayout());

        this._applyLayout();
    },

    toggle() {
        this.layout = this.layout === 2 ? 1 : 2;
        this._applyLayout();

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) {
            fetch('/movies/update-layout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ layout: this.layout })
            }).catch(() => {});
        }
    },

    buttonText() { return this.layout === 1 ? '1 Column' : '2 Columns'; },
    buttonIcon() { return this.layout === 1 ? 'fas fa-th-list mr-2' : 'fas fa-th-large mr-2'; },

    _applyLayout() {
        const grid = document.getElementById('moviesGrid');
        if (!grid) return;

        // On mobile (< 1024px), always show 1 column; on larger screens, use user preference
        const isLargeScreen = this.mediaQuery && this.mediaQuery.matches;
        const effectiveLayout = isLargeScreen ? this.layout : 1;

        // Select movie poster images and placeholder divs
        const images = grid.querySelectorAll('.flex-shrink-0 img, .flex-shrink-0 > a > div, .flex-shrink-0 > div');
        const containers = grid.querySelectorAll('.release-card-container');

        if (effectiveLayout === 1) {
            // Single column layout
            grid.style.gridTemplateColumns = 'repeat(1, minmax(0, 1fr))';
            images.forEach(img => { img.classList.remove('w-32', 'h-48'); img.classList.add('w-48', 'h-72'); });
            containers.forEach(c => {
                c.classList.remove('flex-col', 'space-y-2');
                c.classList.add('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');
                const info = c.querySelector('.release-info-wrapper');
                if (info) info.classList.add('flex-1', 'min-w-0');
                const acts = c.querySelector('.release-actions');
                if (acts) { acts.classList.remove('flex-wrap', 'mt-2'); acts.classList.add('flex-shrink-0', 'flex-row', 'items-center'); }
            });
        } else {
            // Two column layout
            grid.style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
            images.forEach(img => { img.classList.remove('w-48', 'h-72'); img.classList.add('w-32', 'h-48'); });
            containers.forEach(c => {
                c.classList.add('flex', 'flex-col', 'space-y-2');
                c.classList.remove('flex-row', 'items-start', 'justify-between', 'gap-3');
                const info = c.querySelector('.release-info-wrapper');
                if (info) info.classList.remove('flex-1', 'min-w-0');
                const acts = c.querySelector('.release-actions');
                if (acts) { acts.classList.add('flex-wrap', 'items-center', 'mt-2'); acts.classList.remove('flex-shrink-0', 'flex-row'); }
            });
        }
    }
}));
