/**
 * Alpine.data('moviesPage') - Movies page layout toggle
 * Allows switching between 1 and 2 column layouts with persistence via AJAX
 */
import Alpine from '@alpinejs/csp';

Alpine.data('moviesPage', () => ({
    layout: 2,
    isUpdating: false,

    init() {
        // Read initial layout from data attribute if set
        const layoutAttr = this.$el.dataset.movieLayout;
        if (layoutAttr) {
            this.layout = parseInt(layoutAttr, 10) || 2;
        }
    },

    get layoutLabel() {
        return this.layout === 1 ? '1 Column' : '2 Columns';
    },

    get layoutIcon() {
        return this.layout === 1 ? 'fas fa-th-list' : 'fas fa-th-large';
    },

    toggleLayout() {
        if (this.isUpdating) return;
        this.isUpdating = true;

        const newLayout = this.layout === 1 ? 2 : 1;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (csrf) {
            fetch('/movies/update-layout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ layout: newLayout })
            })
            .then(response => {
                if (response.ok) {
                    // Reload page to apply new layout
                    window.location.reload();
                } else {
                    this.isUpdating = false;
                }
            })
            .catch(() => {
                this.isUpdating = false;
            });
        }
    }
}));

