/**
 * Alpine.data('releaseReport') - Shared release report modal (singleton)
 * Alpine.data('adminReleaseReports') - Admin release reports page
 *
 * Uses document-level click delegation in init() so that .report-trigger
 * buttons anywhere on the page can open the single shared modal.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('releaseReport', () => ({
    open: false,
    releaseId: '',
    reason: '',
    description: '',
    isSubmitting: false,
    errorMsg: '',
    successMsg: '',
    _currentTrigger: null,

    openModal(releaseId, trigger) {
        this.releaseId = releaseId;
        this._currentTrigger = trigger || null;
        this.reason = '';
        this.description = '';
        this.errorMsg = '';
        this.successMsg = '';
        this.isSubmitting = false;
        this.open = true;
    },

    close() {
        this.open = false;
    },

    charCount() {
        return this.description.length + '/1000 characters';
    },

    canSubmit() {
        return !!this.reason && !this.isSubmitting;
    },

    submitText() {
        return this.isSubmitting ? 'Submitting...' : 'Submit Report';
    },

    submit() {
        if (this.isSubmitting || !this.reason || !this.releaseId) return;
        this.isSubmitting = true;
        this.errorMsg = '';
        this.successMsg = '';

        var self = this;
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var triggerRef = this._currentTrigger;

        fetch('/release-report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ release_id: self.releaseId, reason: self.reason, description: self.description })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(result) {
            if (result.ok && result.data.success) {
                self.successMsg = result.data.message;
                // Mark the trigger button as reported
                if (triggerRef) {
                    triggerRef.disabled = true;
                    triggerRef.classList.add('opacity-50', 'cursor-not-allowed');
                    var icon = triggerRef.querySelector('i');
                    if (icon) icon.classList.add('text-red-500');
                    var label = triggerRef.querySelector('.report-label');
                    if (label) label.textContent = 'Reported';
                }
                setTimeout(function() { self.close(); }, 2000);
            } else {
                self.errorMsg = result.data.message || 'An error occurred. Please try again.';
            }
        })
        .catch(function() { self.errorMsg = 'Network error. Please try again.'; })
        .finally(function() { self.isSubmitting = false; });
    },

    init() {
        var self = this;

        // Document-level click delegation for .report-trigger buttons
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('.report-trigger');
            if (!trigger) return;

            e.preventDefault();
            e.stopPropagation();

            var releaseId = trigger.getAttribute('data-report-release-id');
            if (!releaseId) return;

            self.openModal(releaseId, trigger);
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.open) self.close();
        });
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

