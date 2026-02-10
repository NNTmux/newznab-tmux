/**
 * Alpine.data('imageModal') - Image preview modal (details page)
 */
import Alpine from '@alpinejs/csp';

Alpine.data('imageModal', () => ({
    open: false,
    imageUrl: '',
    imageTitle: 'Image Preview',

    openModal(url, title) {
        this.imageUrl = url || '';
        this.imageTitle = title || 'Image Preview';
        this.open = true;
    },

    close() {
        this.open = false;
        this.imageUrl = '';
    },

    init() {
        const self = this;
        window.openImageModal = function(url, title) { self.openModal(url, title); };
        window.closeImageModal = function() { self.close(); };

        // Document-level click delegation for image modal triggers
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.image-modal-trigger');
            if (trigger) { e.preventDefault(); self.openModal(trigger.dataset.imageUrl, trigger.dataset.imageTitle); return; }
            if (e.target.closest('[data-close-image-modal]')) { e.preventDefault(); self.close(); }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
    }
}));
