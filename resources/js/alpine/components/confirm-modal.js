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
        if (this.type === 'danger') return 'fa-exclamation-triangle text-red-600 dark:text-red-400';
        if (this.type === 'warning') return 'fa-exclamation-circle text-yellow-600 dark:text-yellow-400';
        if (this.type === 'success') return 'fa-check-circle text-green-600 dark:text-green-400';
        return 'fa-info-circle text-blue-600 dark:text-blue-400';
    },

    confirmBtnClass() {
        const base = 'px-4 py-2 text-white rounded-lg transition font-medium ';
        if (this.type === 'danger') return base + 'bg-red-600 dark:bg-red-700 hover:bg-red-700 dark:hover:bg-red-800';
        if (this.type === 'warning') return base + 'bg-yellow-600 dark:bg-yellow-700 hover:bg-yellow-700 dark:hover:bg-yellow-800';
        if (this.type === 'success') return base + 'bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800';
        return base + 'bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800';
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
                var form = isAnchor ? null : deleteEl.closest('form');
                self.show({
                    message: 'Are you sure you want to delete this item?',
                    type: 'danger',
                    confirmText: 'Delete',
                    onConfirm: function() {
                        if (isAnchor && deleteEl.href) window.location.href = deleteEl.href;
                        else if (form) form.submit();
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
                var form = isAnchor ? null : confirmEl.closest('form');
                self.show({
                    message: message,
                    type: 'danger',
                    confirmText: 'Confirm',
                    onConfirm: function() {
                        if (isAnchor && confirmEl.href) window.location.href = confirmEl.href;
                        else if (form) form.submit();
                    }
                });
                return;
            }
        });
    }
}));
