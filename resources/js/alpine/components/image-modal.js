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
        window.openImageModal = function(url) { self.openModal(url); };
        window.closeImageModal = function() { self.close(); };
    }
}));
