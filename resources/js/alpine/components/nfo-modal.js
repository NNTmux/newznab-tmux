/**
 * Alpine.data('nfoModal') - NFO file viewer modal
 */
import Alpine from '@alpinejs/csp';

Alpine.data('nfoModal', () => ({
    open: false,
    content: '',
    loading: false,
    error: false,

    openNfo(guid) {
        if (!guid) return;
        this.open = true;
        this.loading = true;
        this.error = false;
        this.content = '';

        const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
        fetch(baseUrl + '/nfo/' + guid + '?modal=1')
            .then(response => {
                if (!response.ok) throw new Error('NFO not found');
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                this.content = doc.querySelector('pre')?.textContent || doc.body.textContent;
                this.loading = false;
            })
            .catch(() => {
                this.error = true;
                this.loading = false;
            });
    },

    close() {
        this.open = false;
        this.content = '';
    },

    init() {
        // Backward compat
        const self = this;
        window.openNfoModal = function(guid) { self.openNfo(guid); };
        window.closeNfoModal = function() { self.close(); };
    }
}));
