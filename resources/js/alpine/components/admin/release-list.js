/**
 * Alpine.data('adminReleaseList') - Admin release list bulk selection and category change
 */
import Alpine from '@alpinejs/csp';

Alpine.data('adminReleaseList', () => ({
    allChecked: false,
    selectedCount: 0,
    rootEl: null,

    init() {
        this.rootEl = this.$root;
        this.syncSelectionState();
    },

    componentRoot() {
        return this.rootEl || this.$root;
    },

    releaseCheckboxes() {
        const root = this.componentRoot();

        return root ? [...root.querySelectorAll('.release-checkbox')] : [];
    },

    setAllSelection(checked) {
        const boxes = this.releaseCheckboxes();
        boxes.forEach(cb => {
            cb.checked = checked;
        });
        this.allChecked = checked && boxes.length > 0;
        this.selectedCount = checked ? boxes.length : 0;
    },

    selectAll() {
        this.setAllSelection(true);
    },

    clearSelection() {
        this.setAllSelection(false);
    },

    syncSelectionState() {
        const boxes = this.releaseCheckboxes();
        const checkedCount = boxes.filter(cb => cb.checked).length;
        this.selectedCount = checkedCount;
        this.allChecked = boxes.length > 0 && checkedCount === boxes.length;
    },

    onCheckboxChange() {
        this.syncSelectionState();
    },

    showModal(options) {
        if (typeof window.showConfirm === 'function') {
            window.showConfirm(options);

            return;
        }

        if (options.onConfirm) {
            options.onConfirm();
        }
    },

    showToast(message, type) {
        Alpine.store('toast').show(message, type);
    },

    deleteRelease(e) {
        e.preventDefault();

        const button = e.currentTarget;
        const deleteUrl = button.dataset.deleteUrl;

        if (!deleteUrl) {
            this.showToast('Release delete URL is missing.', 'error');

            return;
        }

        this.showModal({
            title: 'Delete Release',
            message: 'Are you sure you want to delete this release?',
            details: 'This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: () => this.submitReleaseDelete(button, deleteUrl),
        });
    },

    submitReleaseDelete(button, deleteUrl) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!csrf) {
            this.showToast('Unable to delete release: CSRF token is missing.', 'error');

            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        this.showToast('Deleting release...', 'info');

        fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
        })
            .then(response => response.json().catch(() => ({})).then(data => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'Error deleting release: ' + response.status);
                }

                const row = button.closest('tr');
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        this.syncSelectionState();
                    }, 300);
                } else {
                    this.syncSelectionState();
                }

                this.showToast(data.message || 'Release deleted successfully', 'success');
            })
            .catch(error => {
                button.disabled = false;
                this.showToast(error.message || 'Error deleting release.', 'error');
            })
            .finally(() => {
                button.removeAttribute('aria-busy');
            });
    },

    validateBulkAction(e) {
        e.preventDefault();

        const form = e.target;
        const root = this.componentRoot();
        const categorySelect = root?.querySelector('select[name="categories_id"]');
        const categoryId = categorySelect?.value;
        const categoryName = categorySelect?.selectedOptions?.[0]?.text?.trim() || '';
        const checkedCount = this.releaseCheckboxes().filter(cb => cb.checked).length;

        if (!categoryId || categoryId === '-1') {
            this.showModal({
                title: 'Category required',
                message: 'Please select a category to assign to the selected releases.',
                type: 'warning',
                confirmText: 'OK',
                cancelText: 'Close',
                onConfirm: function() {},
            });

            return;
        }

        if (checkedCount === 0) {
            this.showModal({
                title: 'No releases selected',
                message: 'Please select at least one release before changing its category.',
                type: 'warning',
                confirmText: 'OK',
                cancelText: 'Close',
                onConfirm: function() {},
            });

            return;
        }

        this.showModal({
            title: 'Change category',
            message: 'Change category for ' + checkedCount + ' release(s)?',
            details: 'Assign to: ' + categoryName + '. The database and search index will be updated.',
            type: 'warning',
            confirmText: 'Change category',
            cancelText: 'Cancel',
            onConfirm: function() {
                form.submit();
            },
        });
    },
}));
