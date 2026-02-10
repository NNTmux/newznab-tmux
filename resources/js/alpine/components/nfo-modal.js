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
        // Backward compat global functions
        const self = this;
        window.openNfoModal = function(guid) { self.openNfo(guid); };
        window.closeNfoModal = function() { self.close(); };

        // Document-level click delegation for NFO triggers
        document.addEventListener('click', function(e) {
            const nfoAttr = e.target.closest('[data-open-nfo]');
            if (nfoAttr) { e.preventDefault(); self.openNfo(nfoAttr.getAttribute('data-open-nfo')); return; }
            const nfoBadge = e.target.closest('.nfo-badge');
            if (nfoBadge) { e.preventDefault(); self.openNfo(nfoBadge.dataset.guid); return; }
            if (e.target.closest('[data-close-nfo-modal]')) { e.preventDefault(); self.close(); }
        });

        // Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
    }
}));
