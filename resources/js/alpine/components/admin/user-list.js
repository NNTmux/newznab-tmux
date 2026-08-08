/**
 * Alpine.data('adminUserList') - Admin user list bulk selection and scroll sync.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('adminUserList', () => ({
    allChecked: false,

    init() {
        const top = document.getElementById('topScroll');
        const bottom = document.getElementById('bottomScroll');
        const content = document.getElementById('topScrollContent');
        const table = bottom?.querySelector('table');
        if (top && bottom && content && table) {
            const sync = () => { content.style.width = table.scrollWidth + 'px'; };
            sync();
            window.addEventListener('resize', sync);
            top.addEventListener('scroll', function() { if (!top._syncing) { bottom._syncing = true; bottom.scrollLeft = top.scrollLeft; bottom._syncing = false; } });
            bottom.addEventListener('scroll', function() { if (!bottom._syncing) { top._syncing = true; top.scrollLeft = bottom.scrollLeft; bottom._syncing = false; } });
        }

        const selectAll = this.$el.querySelector('#selectAllUserList');
        const form = this.$el.querySelector('#bulkUserActionForm');

        if (selectAll) {
            selectAll.addEventListener('change', () => this.toggleAll());
        }

        this.$el.querySelectorAll('.user-list-checkbox').forEach(cb => {
            cb.addEventListener('change', () => this.onCheckboxChange());
        });

        if (form) {
            form.addEventListener('submit', e => this.submitBulkAction(e));
        }

        // Verify-user trigger buttons (modal lives in the separate verifyUser component)
        this.$el.addEventListener('click', e => {
            const verifyBtn = e.target.closest('[data-show-verify-modal]');
            if (!verifyBtn) {
                return;
            }
            e.preventDefault();
            const verifyForm = verifyBtn.closest('form');
            if (verifyForm && typeof window.showVerifyModal === 'function') {
                window.showVerifyModal(e, verifyForm);
            }
        });
    },

    _checkboxes() {
        return Array.from(this.$el.querySelectorAll('.user-list-checkbox:not(:disabled)'));
    },

    _checkedBoxes() {
        return this._checkboxes().filter(cb => cb.checked);
    },

    _checkedUnverifiedBoxes() {
        return this._checkedBoxes().filter(cb => cb.dataset.isVerified === '0');
    },

    _setValidationError(message) {
        const errEl = document.getElementById('validationError');
        const errMsg = document.getElementById('validationErrorMessage');
        if (errEl && errMsg) {
            errMsg.textContent = message;
            errEl.classList.remove('hidden');
        }
    },

    _clearValidationError() {
        const errEl = document.getElementById('validationError');
        if (errEl) errEl.classList.add('hidden');
    },

    toggleAll() {
        const selectAll = this.$el.querySelector('#selectAllUserList');
        this._checkboxes().forEach(cb => { cb.checked = selectAll?.checked ?? false; });
        this.onCheckboxChange();
    },

    onCheckboxChange() {
        const selectAll = this.$el.querySelector('#selectAllUserList');
        const boxes = this._checkboxes();
        const checked = this._checkedBoxes();
        this.allChecked = boxes.length > 0 && checked.length === boxes.length;

        if (selectAll) {
            selectAll.checked = this.allChecked;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    },

    submitBulkAction(e) {
        e.preventDefault();
        this._clearValidationError();

        const form = this.$el.querySelector('#bulkUserActionForm');
        const action = this.$el.querySelector('#bulkUserAction')?.value;
        const checked = this._checkedBoxes();
        const checkedCount = checked.length;

        if (!action) { this._setValidationError('Please select an action.'); return; }
        if (checkedCount === 0) { this._setValidationError('Please select at least one non-admin active user.'); return; }

        const actions = {
            delete: {
                title: 'Soft Delete Users',
                actionText: 'soft-delete',
                type: 'danger',
                confirmText: 'Soft Delete',
            },
            verify: {
                title: 'Verify Users',
                actionText: 'mark as verified',
                type: 'success',
                confirmText: 'Verify',
            },
            resend_verification: {
                title: 'Resend Verification Emails',
                actionText: 'send account verification emails to',
                type: 'warning',
                confirmText: 'Send Emails',
            },
        };

        const config = actions[action];
        if (!config) { this._setValidationError('Please select a valid action.'); return; }

        let targetCount = checkedCount;
        let message = 'Are you sure you want to ' + config.actionText + ' ' + targetCount + ' user(s)?';

        if (action === 'resend_verification') {
            const unverifiedCount = this._checkedUnverifiedBoxes().length;
            const skippedCount = checkedCount - unverifiedCount;

            if (unverifiedCount === 0) {
                this._setValidationError('Please select at least one not verified user to resend verification email.');
                return;
            }

            targetCount = unverifiedCount;
            message = 'Are you sure you want to send account verification emails to ' + targetCount + ' not verified user(s)?';

            if (skippedCount > 0) {
                message += ' ' + skippedCount + ' already verified user(s) will be skipped.';
            }
        }

        showConfirm({
            title: config.title,
            message: message,
            type: config.type,
            confirmText: config.confirmText,
            onConfirm: function() { if (form) form.submit(); },
        });
    },
}));
