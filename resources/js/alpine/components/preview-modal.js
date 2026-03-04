/**
 * Alpine.data('previewModal') - Preview/sample image modal for browse pages
 */
import Alpine from '@alpinejs/csp';

const prefetchedUrls = new Set();

function buildImageUrl(guid, type) {
    return '/covers/' + (type || 'preview') + '/' + guid + '_thumb.jpg';
}

function prefetchImage(guid, type) {
    const url = buildImageUrl(guid, type);
    if (!prefetchedUrls.has(url)) {
        const img = new Image();
        img.src = url;
        prefetchedUrls.add(url);
    }
}

Alpine.data('previewModal', () => ({
    open: false,
    title: 'Preview Image',
    imageUrl: '',
    imageError: false,
    imageLoaded: false,

    show(guid, type) {
        type = type || 'preview';
        this.title = type === 'sample' ? 'Sample Image' : 'Preview Image';
        const newUrl = buildImageUrl(guid, type);

        if (this.imageUrl === newUrl) {
            this.open = true;
            return;
        }

        this.imageUrl = newUrl;
        this.imageError = false;
        this.imageLoaded = prefetchedUrls.has(newUrl);
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
    },

    errorMessage() {
        return this.title.replace(' Image', '') + ' image not available';
    },

    init() {
        const self = this;
        window.showPreviewImage = function(guid, type) { self.show(guid, type); };
        window.closePreviewModal = function() { self.close(); };

        document.addEventListener('click', function(e) {
            const preview = e.target.closest('.preview-badge');
            if (preview) { e.preventDefault(); self.show(preview.dataset.guid, 'preview'); return; }
            const sample = e.target.closest('.sample-badge');
            if (sample) { e.preventDefault(); self.show(sample.dataset.guid, 'sample'); return; }
            if (e.target.closest('[data-close-preview-modal]')) { e.preventDefault(); self.close(); }
        });

        // Prefetch on hover so the image is cached before click
        document.addEventListener('mouseover', function(e) {
            const preview = e.target.closest('.preview-badge');
            if (preview) { prefetchImage(preview.dataset.guid, 'preview'); return; }
            const sample = e.target.closest('.sample-badge');
            if (sample) { prefetchImage(sample.dataset.guid, 'sample'); }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });

        // Prefetch images for badges visible in the viewport during idle time
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        const type = el.classList.contains('sample-badge') ? 'sample' : 'preview';
                        const guid = el.dataset.guid;
                        if (typeof requestIdleCallback === 'function') {
                            requestIdleCallback(function() { prefetchImage(guid, type); });
                        } else {
                            setTimeout(function() { prefetchImage(guid, type); }, 200);
                        }
                        observer.unobserve(el);
                    }
                });
            }, { rootMargin: '200px' });

            document.querySelectorAll('.sample-badge, .preview-badge').forEach(function(el) {
                observer.observe(el);
            });
        }
    }
}));
