/**
 * Alpine.data('releaseReport') - Release report modal per-release
 * Alpine.data('adminReleaseReports') - Admin release reports page
 */
import Alpine from '@alpinejs/csp';

Alpine.data('releaseReport', () => ({
    open: false,
    hasReported: false,
    isSubmitting: false,
    reason: '',
    description: '',
    errorMsg: '',
    successMsg: '',

    openModal() {
        if (this.hasReported) return;
        this.open = true;
        this.reason = '';
        this.description = '';
        this.errorMsg = '';
        this.successMsg = '';
    },

    close() {
        this.open = false;
    },

    charCount() { return this.description.length + '/1000 characters'; },
    canSubmit() { return !!this.reason && !this.isSubmitting; },

    submit(releaseId) {
        if (this.isSubmitting || !this.reason) return;
        this.isSubmitting = true;
        this.errorMsg = '';
        this.successMsg = '';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/release-report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ release_id: releaseId, reason: this.reason, description: this.description })
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(result => {
            if (result.ok && result.data.success) {
                this.successMsg = result.data.message;
                this.hasReported = true;
                setTimeout(() => this.close(), 2000);
            } else {
                this.errorMsg = result.data.message || 'An error occurred. Please try again.';
            }
        })
        .catch(() => { this.errorMsg = 'Network error. Please try again.'; })
        .finally(() => { this.isSubmitting = false; });
    }
}));

Alpine.data('adminReleaseReports', () => ({
    descModalOpen: false,
    descContent: '',
    descReason: '',
    descReporter: '',
    revertModalOpen: false,
    revertActionUrl: '',
    revertStatus: '',
    allChecked: false,

    showDescription(description, reason, reporter) {
        this.descContent = description || 'No additional details provided.';
        this.descReason = reason || 'Unknown';
        this.descReporter = reporter || 'Unknown';
        this.descModalOpen = true;
    },

    closeDescription() { this.descModalOpen = false; },

    showRevert(actionUrl, status) {
        this.revertActionUrl = actionUrl;
        this.revertStatus = status;
        this.revertModalOpen = true;
    },

    closeRevert() { this.revertModalOpen = false; },

    submitRevert() {
        const form = this.$refs.revertForm;
        if (form) { form.action = this.revertActionUrl; form.submit(); }
    },

    toggleAll() {
        const boxes = this.$el.querySelectorAll('.report-checkbox');
        boxes.forEach(cb => { cb.checked = this.allChecked; });
    },

    onCheckboxChange() {
        const boxes = this.$el.querySelectorAll('.report-checkbox');
        const checked = this.$el.querySelectorAll('.report-checkbox:checked');
        this.allChecked = boxes.length > 0 && checked.length === boxes.length;
    },

    validateBulkAction(e) {
        const action = this.$el.querySelector('select[name="action"]')?.value;
        const checkedCount = this.$el.querySelectorAll('.report-checkbox:checked').length;
        if (!action) { e.preventDefault(); alert('Please select an action.'); return; }
        if (checkedCount === 0) { e.preventDefault(); alert('Please select at least one report.'); return; }
        if (action === 'delete') {
            if (!confirm('Are you sure you want to DELETE ' + checkedCount + ' release(s)? This action cannot be undone.')) e.preventDefault();
        }
    }
}));
