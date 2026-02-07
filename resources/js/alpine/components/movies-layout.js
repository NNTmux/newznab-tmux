/**
 * Alpine.data('moviesLayout') - Movies page layout toggle (1/2 columns)
 */
import Alpine from '@alpinejs/csp';

Alpine.data('moviesLayout', () => ({
    layout: 2,

    init() {
        const grid = document.getElementById('moviesGrid');
        if (grid) this.layout = parseInt(grid.dataset.userLayout) || 2;
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
        const images = grid.querySelectorAll('img[alt], .bg-gray-200.dark\\:bg-gray-700');
        const containers = grid.querySelectorAll('.release-card-container');

        if (this.layout === 1) {
            grid.classList.remove('lg:grid-cols-2');
            grid.classList.add('grid-cols-1');
            images.forEach(img => { img.classList.remove('w-32', 'h-48'); img.classList.add('w-48', 'h-72'); });
            containers.forEach(c => {
                c.classList.remove('space-y-2');
                c.classList.add('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');
                const info = c.querySelector('.release-info-wrapper');
                if (info) info.classList.add('flex-1', 'min-w-0');
                const acts = c.querySelector('.release-actions');
                if (acts) { acts.classList.remove('flex-wrap'); acts.classList.add('flex-shrink-0', 'flex-row', 'items-center'); }
            });
        } else {
            grid.classList.remove('grid-cols-1');
            grid.classList.add('lg:grid-cols-2');
            images.forEach(img => { img.classList.remove('w-48', 'h-72'); img.classList.add('w-32', 'h-48'); });
            containers.forEach(c => {
                c.classList.add('space-y-2');
                c.classList.remove('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');
                const info = c.querySelector('.release-info-wrapper');
                if (info) info.classList.remove('flex-1', 'min-w-0');
                const acts = c.querySelector('.release-actions');
                if (acts) { acts.classList.add('flex-wrap', 'items-center'); acts.classList.remove('flex-shrink-0', 'flex-row'); }
            });
        }
    }
}));
