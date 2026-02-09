/**
 * Alpine.data('confirmLink') - Confirm before navigating to a URL
 * Uses the styled confirmation modal instead of native confirm()
 * Usage: x-data="confirmLink" data-url="/path" data-message="Are you sure?" data-title="Confirm" data-type="danger"
 */
import Alpine from '@alpinejs/csp';

Alpine.data('confirmLink', () => ({
    url: '',
    message: 'Are you sure?',
    title: 'Confirm Action',
    type: 'danger',
    confirmText: 'Delete',
    cancelText: 'Cancel',

    init() {
        this.url = this.$el.dataset.url || this.$el.href || '';
        this.message = this.$el.dataset.message || 'Are you sure?';
        this.title = this.$el.dataset.title || 'Confirm Action';
        this.type = this.$el.dataset.type || 'danger';
        this.confirmText = this.$el.dataset.confirmText || 'Delete';
        this.cancelText = this.$el.dataset.cancelText || 'Cancel';
    },

    navigate() {
        const url = this.url;
        if (typeof window.showConfirm === 'function') {
            window.showConfirm({
                title: this.title,
                message: this.message,
                type: this.type,
                confirmText: this.confirmText,
                cancelText: this.cancelText,
                onConfirm: function() {
                    window.location.href = url;
                }
            });
        } else {
            // Fallback to native confirm if modal not available
            if (confirm(this.message)) {
                window.location.href = url;
            }
        }
    }
}));

/**
 * Alpine.data('confirmForm') - Confirm before submitting a form
 * Uses the styled confirmation modal instead of native confirm()
 * Usage on form: x-data="confirmForm" data-message="Are you sure?" data-title="Confirm" data-type="warning"
 */
Alpine.data('confirmForm', () => ({
    message: 'Are you sure?',
    title: 'Confirm Action',
    type: 'warning',
    confirmText: 'Confirm',
    cancelText: 'Cancel',

    init() {
        this.message = this.$el.dataset.message || 'Are you sure?';
        this.title = this.$el.dataset.title || 'Confirm Action';
        this.type = this.$el.dataset.type || 'warning';
        this.confirmText = this.$el.dataset.confirmText || 'Confirm';
        this.cancelText = this.$el.dataset.cancelText || 'Cancel';
    },

    submit() {
        const form = this.$el;
        if (typeof window.showConfirm === 'function') {
            window.showConfirm({
                title: this.title,
                message: this.message,
                type: this.type,
                confirmText: this.confirmText,
                cancelText: this.cancelText,
                onConfirm: function() {
                    form.submit();
                }
            });
        } else {
            // Fallback to native confirm if modal not available
            if (confirm(this.message)) {
                form.submit();
            }
        }
    }
}));

