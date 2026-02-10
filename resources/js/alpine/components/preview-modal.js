/**
 * Alpine.data('previewModal') - Preview/sample image modal for browse pages
 */
import Alpine from '@alpinejs/csp';

Alpine.data('previewModal', () => ({
    open: false,
    title: 'Preview Image',
    imageUrl: '',
    imageError: false,
    imageLoaded: false,

    show(guid, type) {
        type = type || 'preview';
        this.title = type === 'sample' ? 'Sample Image' : 'Preview Image';
        this.imageUrl = '/covers/' + type + '/' + guid + '_thumb.jpg';
        this.imageError = false;
        this.imageLoaded = false;
        this.open = true;
    },

    onImageError() {
        this.imageError = true;
    },

    onImageLoad() {
        this.imageLoaded = true;
    },

    close() {
        this.open = false;
        this.imageUrl = '';
    },

    errorMessage() {
        return this.title.replace(' Image', '') + ' image not available';
    },

    init() {
        const self = this;
        window.showPreviewImage = function(guid, type) { self.show(guid, type); };
        window.closePreviewModal = function() { self.close(); };

        // Document-level click delegation for preview/sample triggers
        document.addEventListener('click', function(e) {
            const preview = e.target.closest('.preview-badge');
            if (preview) { e.preventDefault(); self.show(preview.dataset.guid, 'preview'); return; }
            const sample = e.target.closest('.sample-badge');
            if (sample) { e.preventDefault(); self.show(sample.dataset.guid, 'sample'); return; }
            if (e.target.closest('[data-close-preview-modal]')) { e.preventDefault(); self.close(); }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
    }
}));
