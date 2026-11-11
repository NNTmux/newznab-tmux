/**
 * Alpine.data('confirmModal') - Styled confirmation modal (replaces native confirm)
 * Global singleton - one per page, invoked via window.showConfirm() or $dispatch.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('confirmModal', () => ({
    open: false,
    title: 'Confirm Action',
    message: 'Are you sure you want to proceed?',
    details: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    type: 'info', // info, warning, danger, success
    _onConfirm: null,
    _resolve: null,

    show(options) {
        const defaults = {
            title: 'Confirm Action',
            message: 'Are you sure you want to proceed?',
            details: '',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            type: 'info',
            onConfirm: null
        };
        const config = Object.assign({}, defaults, options);

        this.title = config.title;
        this.message = config.message;
        this.details = config.details;
        this.confirmText = config.confirmText;
        this.cancelText = config.cancelText;
        this.type = config.type;
        this._onConfirm = config.onConfirm;
        this.open = true;

        return new Promise(resolve => { this._resolve = resolve; });
    },

    confirm() {
        this.open = false;
        if (this._onConfirm) this._onConfirm();
        if (this._resolve) this._resolve(true);
        this._onConfirm = null;
        this._resolve = null;
    },

    cancel() {
        this.open = false;
        if (this._resolve) this._resolve(false);
        this._onConfirm = null;
        this._resolve = null;
    },

    iconClass() {
        if (this.type === 'danger') return 'fa-exclamation-triangle text-danger-600 dark:text-danger-400';
        if (this.type === 'warning') return 'fa-exclamation-circle text-warning-600 dark:text-warning-400';
        if (this.type === 'success') return 'fa-check-circle text-success-600 dark:text-success-400';
        return 'fa-info-circle text-link dark:text-primary-400';
    },

    confirmBtnClass() {
        const base = 'px-4 py-2 text-white rounded-lg transition font-medium ';
        if (this.type === 'danger') return base + 'bg-danger-600 dark:bg-danger-700 hover:bg-danger-700 dark:hover:bg-danger-800';
        if (this.type === 'warning') return base + 'bg-warning-600 dark:bg-warning-700 hover:bg-warning-700 dark:hover:bg-warning-800';
        if (this.type === 'success') return base + 'bg-success-600 dark:bg-success-700 hover:bg-success-700 dark:hover:bg-success-800';
        return base + 'bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800';
    },

    init() {
        // Global showConfirm for backward compatibility + non-Alpine callers
        const self = this;
        window.showConfirm = function(options) { return self.show(options); };
        window.closeConfirmationModal = function() { self.cancel(); };
        window.confirmConfirmationModal = function() { self.confirm(); };

        // Document-level delegation for data-confirm, data-confirm-delete, data-logout
        document.addEventListener('click', function(e) {
            // Close/confirm buttons
            if (e.target.closest('[data-close-confirmation-modal]')) { e.preventDefault(); self.cancel(); return; }
            if (e.target.closest('[data-confirm-confirmation-modal]')) { e.preventDefault(); self.confirm(); return; }

            // Logout
            var logoutEl = e.target.closest('[data-logout]');
            if (logoutEl) {
                e.preventDefault();
                var form = document.getElementById('logout-form') || document.getElementById('sidebar-logout-form');
                if (form) form.submit();
                return;
            }

            // data-confirm-delete
            var deleteEl = e.target.closest('[data-confirm-delete]');
            if (deleteEl) {
                e.preventDefault();
                e.stopPropagation();
                var isAnchor = deleteEl.tagName === 'A';
                var form = isAnchor ? null : (deleteEl.form || deleteEl.closest('form'));
                self.show({
                    message: 'Are you sure you want to delete this item?',
                    type: 'danger',
                    confirmText: 'Delete',
                    onConfirm: function() {
                        if (isAnchor && deleteEl.href) window.location.href = deleteEl.href;
                        else if (form) form.requestSubmit(deleteEl);
                    }
                });
                return;
            }

            // data-confirm
            var confirmEl = e.target.closest('[data-confirm]');
            if (confirmEl) {
                e.preventDefault();
                e.stopPropagation();
                var message = confirmEl.getAttribute('data-confirm');
                var isAnchor = confirmEl.tagName === 'A';
                var form = isAnchor ? null : (confirmEl.form || confirmEl.closest('form'));
                var options = {};
                if (confirmEl.hasAttribute('data-confirm-title')) options.title = confirmEl.getAttribute('data-confirm-title');
                self.show(Object.assign(options, {
                    message: message,
                    // data-confirm-type lets non-destructive actions (restore, verify, resend) avoid the danger styling
                    type: confirmEl.getAttribute('data-confirm-type') || 'danger',
                    confirmText: confirmEl.getAttribute('data-confirm-text') || 'Confirm',
                    onConfirm: function() {
                        if (isAnchor && confirmEl.href) window.location.href = confirmEl.href;
                        else if (form) form.requestSubmit(confirmEl);
                    }
                }));
                return;
            }
        });
    }
}));
