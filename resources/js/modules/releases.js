/**
 * Release reports module
 * Extracted from csp-safe.js
 */

export function initReleaseReportButtons() {
    // Find all report button containers
    var containers = document.querySelectorAll('.report-button-container');

    containers.forEach(function(container) {
        var trigger = container.querySelector('.report-trigger');
        var modal = container.querySelector('.report-modal');

        if (!trigger || !modal) return;

        // Skip if already initialized
        if (trigger.hasAttribute('data-report-initialized')) return;
        trigger.setAttribute('data-report-initialized', 'true');

        var releaseId = trigger.getAttribute('data-report-release-id');

        var form = modal.querySelector('.report-form');
        var closeButtons = modal.querySelectorAll('.report-modal-close');
        var backdrop = modal.querySelector('.report-modal-backdrop');
        var reasonSelect = modal.querySelector('.report-reason');
        var descriptionTextarea = modal.querySelector('.report-description');
        var charCount = modal.querySelector('.report-char-count');
        var submitButton = modal.querySelector('.report-submit');
        var errorDiv = modal.querySelector('.report-error');
        var successDiv = modal.querySelector('.report-success');
        var reportLabel = trigger.querySelector('.report-label');
        var flagIcon = trigger.querySelector('i');

        var hasReported = false;
        var isSubmitting = false;

        // Open modal
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (hasReported) return;

            modal.classList.remove('hidden');
            // Reset form state
            if (errorDiv) errorDiv.classList.add('hidden');
            if (successDiv) successDiv.classList.add('hidden');
            if (reasonSelect) reasonSelect.value = '';
            if (descriptionTextarea) descriptionTextarea.value = '';
            if (charCount) charCount.textContent = '0/1000 characters';
            if (submitButton) submitButton.disabled = true;
        });

        // Close modal function
        function closeModal() {
            modal.classList.add('hidden');
            if (hasReported) {
                trigger.disabled = true;
                trigger.classList.add('opacity-50', 'cursor-not-allowed');
                if (flagIcon) flagIcon.classList.add('text-red-500');
                if (reportLabel) reportLabel.textContent = 'Reported';
            }
        }

        // Close button handlers
        closeButtons.forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal();
            });
        });

        // Backdrop click to close
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                closeModal();
            });
        }

        // Character count for description
        if (descriptionTextarea && charCount) {
            descriptionTextarea.addEventListener('input', function() {
                charCount.textContent = this.value.length + '/1000 characters';
            });
        }

        // Enable/disable submit based on reason selection
        if (reasonSelect && submitButton) {
            reasonSelect.addEventListener('change', function() {
                submitButton.disabled = !this.value;
            });
        }

        // Submit button click handler
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (isSubmitting || !reasonSelect || !reasonSelect.value) return;

                isSubmitting = true;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';

                if (errorDiv) errorDiv.classList.add('hidden');
                if (successDiv) successDiv.classList.add('hidden');

                var csrfToken = document.querySelector('meta[name="csrf-token"]');
                var csrfValue = csrfToken ? csrfToken.getAttribute('content') : '';

                var requestBody = {
                    release_id: releaseId,
                    reason: reasonSelect.value,
                    description: descriptionTextarea ? descriptionTextarea.value : ''
                };

                fetch('/release-report', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfValue,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                })
                .then(function(response) {
                    return response.json().then(function(data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function(result) {
                    if (result.ok && result.data.success) {
                        if (successDiv) {
                            successDiv.querySelector('p').textContent = result.data.message;
                            successDiv.classList.remove('hidden');
                        }
                        hasReported = true;
                        setTimeout(closeModal, 2000);
                    } else {
                        if (errorDiv) {
                            errorDiv.querySelector('p').textContent = result.data.message || 'An error occurred. Please try again.';
                            errorDiv.classList.remove('hidden');
                        }
                    }
                })
                .catch(function(error) {
                    if (errorDiv) {
                        errorDiv.querySelector('p').textContent = 'Network error. Please try again.';
                        errorDiv.classList.remove('hidden');
                    }
                })
                .finally(function() {
                    isSubmitting = false;
                    if (submitButton) {
                        submitButton.disabled = !reasonSelect.value;
                        submitButton.innerHTML = 'Submit Report';
                    }
                });
            });
        }
    });
}

// Initialize report buttons on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initReleaseReportButtons);

// Re-initialize on dynamic content load (for AJAX-loaded content)
document.addEventListener('contentLoaded', initReleaseReportButtons);

/**
 * Initialize admin release reports page functionality
 * Handles description modal, revert confirmation modal, select all, and bulk actions
 */
export function initAdminReleaseReports() {
    // Report Description Modal
    var descModal = document.getElementById('reportDescriptionModal');
    var descButtons = document.querySelectorAll('.report-description-btn');
    var descContent = document.getElementById('reportDescContent');
    var descReason = document.getElementById('reportDescReason');
    var descReporter = document.getElementById('reportDescReporter');
    var descCloseButtons = document.querySelectorAll('.report-desc-modal-close');
    var descBackdrop = document.querySelector('.report-desc-modal-backdrop');

    // Revert Confirmation Modal
    var revertModal = document.getElementById('revertConfirmModal');
    var revertButtons = document.querySelectorAll('.revert-report-btn');
    var revertForm = document.getElementById('revertConfirmForm');
    var revertStatusSpan = document.getElementById('revertReportStatus');
    var revertCloseButtons = document.querySelectorAll('.revert-modal-close');
    var revertBackdrop = document.querySelector('.revert-modal-backdrop');

    // Open revert confirmation modal
    revertButtons.forEach(function(btn) {
        if (btn.hasAttribute('data-revert-initialized')) return;
        btn.setAttribute('data-revert-initialized', 'true');

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var actionUrl = this.getAttribute('data-action-url');
            var status = this.getAttribute('data-report-status');

            if (revertForm) revertForm.setAttribute('action', actionUrl);
            if (revertStatusSpan) revertStatusSpan.textContent = status;

            if (revertModal) revertModal.classList.remove('hidden');
        });
    });

    // Close revert modal
    function closeRevertModal() {
        if (revertModal) revertModal.classList.add('hidden');
    }

    revertCloseButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeRevertModal();
        });
    });

    if (revertBackdrop) {
        revertBackdrop.addEventListener('click', closeRevertModal);
    }

    // Close revert modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRevertModal();
            closeDescModal();
        }
    });

    // Open description modal
    descButtons.forEach(function(btn) {
        if (btn.hasAttribute('data-desc-initialized')) return;
        btn.setAttribute('data-desc-initialized', 'true');

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var description = this.getAttribute('data-description');
            var reason = this.getAttribute('data-reason');
            var reporter = this.getAttribute('data-reporter');

            if (descContent) descContent.textContent = description || 'No additional details provided.';
            if (descReason) descReason.textContent = reason || 'Unknown';
            if (descReporter) descReporter.textContent = reporter || 'Unknown';

            if (descModal) descModal.classList.remove('hidden');
        });
    });

    // Close description modal
    function closeDescModal() {
        if (descModal) descModal.classList.add('hidden');
    }

    descCloseButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeDescModal();
        });
    });

    if (descBackdrop) {
        descBackdrop.addEventListener('click', closeDescModal);
    }

    // Select All functionality
    var selectAll = document.getElementById('select-all');
    var checkboxes = document.querySelectorAll('.report-checkbox');

    if (selectAll && !selectAll.hasAttribute('data-selectall-initialized')) {
        selectAll.setAttribute('data-selectall-initialized', 'true');

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    // Update select all checkbox when individual checkboxes change
    checkboxes.forEach(function(checkbox) {
        if (checkbox.hasAttribute('data-checkbox-initialized')) return;
        checkbox.setAttribute('data-checkbox-initialized', 'true');

        checkbox.addEventListener('change', function() {
            var allChecked = Array.from(checkboxes).every(function(cb) { return cb.checked; });
            var someChecked = Array.from(checkboxes).some(function(cb) { return cb.checked; });
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });

    // Bulk action form validation
    var bulkForm = document.getElementById('bulk-action-form');
    if (bulkForm && !bulkForm.hasAttribute('data-bulk-initialized')) {
        bulkForm.setAttribute('data-bulk-initialized', 'true');

        bulkForm.addEventListener('submit', function(e) {
            var actionSelect = this.querySelector('select[name="action"]');
            var action = actionSelect ? actionSelect.value : '';
            var checkedCount = document.querySelectorAll('.report-checkbox:checked').length;

            if (!action) {
                e.preventDefault();
                alert('Please select an action.');
                return;
            }

            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one report.');
                return;
            }

            if (action === 'delete') {
                if (!confirm('Are you sure you want to DELETE ' + checkedCount + ' release(s)? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    }
}

// Initialize admin release reports on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initAdminReleaseReports);
