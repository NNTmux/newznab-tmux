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
 * Uses a single shared modal (#shared-report-modal) rendered once in the layout,
 * instead of per-element modals (which duplicated ~80 lines of HTML per release).
 */
(function() {
    var modal = null;
    var reasonSelect, descriptionTextarea, charCount, submitButton, errorDiv, successDiv;
    var currentTrigger = null;
    var isSubmitting = false;

    function getModal() {
        if (modal) return modal;
        modal = document.getElementById('shared-report-modal');
        if (!modal) return null;
        reasonSelect = modal.querySelector('.report-reason');
        descriptionTextarea = modal.querySelector('.report-description');
        charCount = modal.querySelector('.report-char-count');
        submitButton = modal.querySelector('.report-submit');
        errorDiv = modal.querySelector('.report-error');
        successDiv = modal.querySelector('.report-success');

        // Wire up events once
        modal.querySelectorAll('.report-modal-close').forEach(function(btn) {
            btn.addEventListener('click', function(ev) { ev.preventDefault(); closeModal(); });
        });
        var backdrop = modal.querySelector('.report-modal-backdrop');
        if (backdrop) backdrop.addEventListener('click', closeModal);
        if (descriptionTextarea && charCount) {
            descriptionTextarea.addEventListener('input', function() {
                charCount.textContent = this.value.length + '/1000 characters';
            });
        }
        if (reasonSelect && submitButton) {
            reasonSelect.addEventListener('change', function() { submitButton.disabled = !this.value; });
        }
        if (submitButton) {
            submitButton.addEventListener('click', function(ev) {
                ev.preventDefault();
                ev.stopPropagation();
                submitReport();
            });
        }
        return modal;
    }

    function closeModal() {
        if (modal) modal.classList.add('hidden');
    }

    function markTriggerReported(trigger) {
        if (!trigger) return;
        trigger.disabled = true;
        trigger.classList.add('opacity-50', 'cursor-not-allowed');
        var icon = trigger.querySelector('i');
        if (icon) icon.classList.add('text-red-500');
        var label = trigger.querySelector('.report-label');
        if (label) label.textContent = 'Reported';
    }

    function submitReport() {
        if (isSubmitting || !reasonSelect || !reasonSelect.value) return;
        var releaseId = modal.getAttribute('data-release-id');
        if (!releaseId) return;

        isSubmitting = true;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
        if (errorDiv) errorDiv.classList.add('hidden');
        if (successDiv) successDiv.classList.add('hidden');

        var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var triggerRef = currentTrigger;

        fetch('/release-report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ release_id: releaseId, reason: reasonSelect.value, description: descriptionTextarea ? descriptionTextarea.value : '' })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(result) {
            if (result.ok && result.data.success) {
                if (successDiv) { successDiv.querySelector('p').textContent = result.data.message; successDiv.classList.remove('hidden'); }
                markTriggerReported(triggerRef);
                setTimeout(closeModal, 2000);
            } else {
                if (errorDiv) { errorDiv.querySelector('p').textContent = result.data.message || 'An error occurred.'; errorDiv.classList.remove('hidden'); }
            }
        })
        .catch(function() { if (errorDiv) { errorDiv.querySelector('p').textContent = 'Network error.'; errorDiv.classList.remove('hidden'); } })
        .finally(function() { isSubmitting = false; if (submitButton) { submitButton.disabled = !reasonSelect.value; submitButton.innerHTML = 'Submit Report'; } });
    }

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.report-trigger');
        if (!trigger) return;

        e.preventDefault();
        e.stopPropagation();

        var m = getModal();
        if (!m) return;

        var releaseId = trigger.getAttribute('data-report-release-id');
        if (!releaseId) return;

        // Store current trigger and set release ID on the shared modal
        currentTrigger = trigger;
        m.setAttribute('data-release-id', releaseId);

        // Reset form state
        if (errorDiv) errorDiv.classList.add('hidden');
        if (successDiv) successDiv.classList.add('hidden');
        if (reasonSelect) reasonSelect.value = '';
        if (descriptionTextarea) descriptionTextarea.value = '';
        if (charCount) charCount.textContent = '0/1000 characters';
        if (submitButton) { submitButton.disabled = true; submitButton.innerHTML = 'Submit Report'; }
        isSubmitting = false;

        m.classList.remove('hidden');
    });
})();
