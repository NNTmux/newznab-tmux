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

/**
 * Document-level delegation for .report-trigger buttons.
 * Handles the report modal open/close/submit flow for buttons without x-data.
 */
document.addEventListener('click', function(e) {
    var reportTrigger = e.target.closest('.report-trigger');
    if (!reportTrigger) return;

    e.preventDefault();
    e.stopPropagation();
    var container = reportTrigger.closest('.report-button-container');
    if (!container) return;
    var modal = container.querySelector('.report-modal');
    if (!modal) return;
    // Skip if already handled by Alpine x-data
    if (modal.hasAttribute('x-data')) return;

    var releaseId = reportTrigger.getAttribute('data-report-release-id');
    var reasonSelect = modal.querySelector('.report-reason');
    var descriptionTextarea = modal.querySelector('.report-description');
    var charCount = modal.querySelector('.report-char-count');
    var submitButton = modal.querySelector('.report-submit');
    var errorDiv = modal.querySelector('.report-error');
    var successDiv = modal.querySelector('.report-success');
    var closeButtons = modal.querySelectorAll('.report-modal-close');
    var backdrop = modal.querySelector('.report-modal-backdrop');
    var reportLabel = reportTrigger.querySelector('.report-label');
    var flagIcon = reportTrigger.querySelector('i');

    // Reset and show
    if (errorDiv) errorDiv.classList.add('hidden');
    if (successDiv) successDiv.classList.add('hidden');
    if (reasonSelect) reasonSelect.value = '';
    if (descriptionTextarea) descriptionTextarea.value = '';
    if (charCount) charCount.textContent = '0/1000 characters';
    if (submitButton) submitButton.disabled = true;
    modal.classList.remove('hidden');

    var hasReported = false;
    var isSubmitting = false;

    function closeReportModal() {
        modal.classList.add('hidden');
        if (hasReported) {
            reportTrigger.disabled = true;
            reportTrigger.classList.add('opacity-50', 'cursor-not-allowed');
            if (flagIcon) flagIcon.classList.add('text-red-500');
            if (reportLabel) reportLabel.textContent = 'Reported';
        }
    }

    if (!modal._bridgeWired) {
        modal._bridgeWired = true;
        closeButtons.forEach(function(btn) { btn.addEventListener('click', function(ev) { ev.preventDefault(); closeReportModal(); }); });
        if (backdrop) backdrop.addEventListener('click', closeReportModal);
        if (descriptionTextarea && charCount) {
            descriptionTextarea.addEventListener('input', function() { charCount.textContent = this.value.length + '/1000 characters'; });
        }
        if (reasonSelect && submitButton) {
            reasonSelect.addEventListener('change', function() { submitButton.disabled = !this.value; });
        }
        if (submitButton) {
            submitButton.addEventListener('click', function(ev) {
                ev.preventDefault();
                ev.stopPropagation();
                if (isSubmitting || !reasonSelect || !reasonSelect.value) return;
                isSubmitting = true;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
                if (errorDiv) errorDiv.classList.add('hidden');
                if (successDiv) successDiv.classList.add('hidden');

                var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch('/release-report', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ release_id: releaseId, reason: reasonSelect.value, description: descriptionTextarea ? descriptionTextarea.value : '' })
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                .then(function(result) {
                    if (result.ok && result.data.success) {
                        if (successDiv) { successDiv.querySelector('p').textContent = result.data.message; successDiv.classList.remove('hidden'); }
                        hasReported = true;
                        setTimeout(closeReportModal, 2000);
                    } else {
                        if (errorDiv) { errorDiv.querySelector('p').textContent = result.data.message || 'An error occurred.'; errorDiv.classList.remove('hidden'); }
                    }
                })
                .catch(function() { if (errorDiv) { errorDiv.querySelector('p').textContent = 'Network error.'; errorDiv.classList.remove('hidden'); } })
                .finally(function() { isSubmitting = false; if (submitButton) { submitButton.disabled = !reasonSelect.value; submitButton.innerHTML = 'Submit Report'; } });
            });
        }
    }
});
