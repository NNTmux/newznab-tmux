/**
 * Admin Groups Management
 */

import { escapeHtml } from '../utils.js';

function initAdminGroups() {
    // Get AJAX URL and CSRF token from page
    const container = document.querySelector('[data-ajax-url]');
    const ajaxUrl = container ? container.dataset.ajaxUrl : '/admin/ajax';
    const csrfToken = container ? container.dataset.csrfToken : document.querySelector('meta[name="csrf-token"]')?.content;

    // Toggle group active/inactive status
    window.ajax_group_status = function(id, status) {
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'toggle_group_active_status',
                group_id: id,
                group_status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const groupCell = document.getElementById('group-' + id);
                if (groupCell && data.newStatus !== undefined) {
                    const isActive = data.newStatus == 1;
                    groupCell.innerHTML = isActive
                        ? `<button type="button" data-action="toggle-group-status" data-group-id="${id}" data-status="0" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 hover:bg-green-200">
                            <i class="fa fa-check-circle mr-1"></i>Active
                           </button>`
                        : `<button type="button" data-action="toggle-group-status" data-group-id="${id}" data-status="1" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                            <i class="fa fa-times-circle mr-1"></i>Inactive
                           </button>`;
                }
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Group status updated', 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error updating group status', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('Error updating group status', 'error');
            }
        });
    };

    // Toggle backfill status
    window.ajax_backfill_status = function(id, status) {
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'toggle_group_backfill',
                group_id: id,
                backfill: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const backfillCell = document.getElementById('backfill-' + id);
                if (backfillCell && data.newStatus !== undefined) {
                    const isEnabled = data.newStatus == 1;
                    backfillCell.innerHTML = isEnabled
                        ? `<button type="button" data-action="toggle-backfill" data-group-id="${id}" data-status="0" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200">
                            <i class="fa fa-check-circle mr-1"></i>Enabled
                           </button>`
                        : `<button type="button" data-action="toggle-backfill" data-group-id="${id}" data-status="1" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200">
                            <i class="fa fa-times-circle mr-1"></i>Disabled
                           </button>`;
                }
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Backfill status updated', 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error updating backfill status', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('Error updating backfill status', 'error');
            }
        });
    };

    // Reset group
    window.ajax_group_reset = function(id) {
        showConfirm({
            title: 'Reset Group',
            message: 'Are you sure you want to reset this group?',
            details: 'This will reset the article pointers back to the current state.',
            type: 'warning',
            confirmText: 'Reset',
            cancelText: 'Cancel',
            onConfirm: function() {
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'reset_group',
                        group_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Group reset successfully', 'success');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Error resetting group', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('Error resetting group', 'error');
                    }
                });
            }
        });
    };

    // Delete group
    window.confirmGroupDelete = function(id) {
        showConfirm({
            title: 'Delete Group',
            message: 'Are you sure you want to delete this group?',
            details: 'This action cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: function() {
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'delete_group',
                        group_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById('grouprow-' + id);
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Group deleted successfully', 'success');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Error deleting group', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('Error deleting group', 'error');
                    }
                });
            }
        });
    };

    // Purge group
    window.confirmGroupPurge = function(id) {
        showConfirm({
            title: 'Purge Group',
            message: 'Are you sure you want to purge this group?',
            details: 'This will delete all releases and binaries for this group. This action cannot be undone!',
            type: 'danger',
            confirmText: 'Purge',
            cancelText: 'Cancel',
            onConfirm: function() {
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'purge_group',
                        group_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Group purged successfully', 'success');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Error purging group', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('Error purging group', 'error');
                    }
                });
            }
        });
    };

    // Reset all groups
    window.ajax_group_reset_all = function() {
        hideResetAllModal();

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'reset_all_groups'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'All groups reset successfully', 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error resetting all groups', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('Error resetting all groups', 'error');
            }
        });
    };

    // Purge all groups
    window.ajax_group_purge_all = function() {
        hidePurgeAllModal();

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'purge_all_groups'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'All groups purged successfully', 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error purging all groups', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('Error purging all groups', 'error');
            }
        });
    };

    // Modal helpers
    window.showResetAllModal = function() {
        const modal = document.getElementById('resetAllModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    };

    window.hideResetAllModal = function() {
        const modal = document.getElementById('resetAllModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    window.showPurgeAllModal = function() {
        const modal = document.getElementById('purgeAllModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    };

    window.hidePurgeAllModal = function() {
        const modal = document.getElementById('purgeAllModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Reset Selected Modal helpers
    window.showResetSelectedModal = function() {
        const modal = document.getElementById('resetSelectedModal');
        const countSpan = document.getElementById('reset-selected-count');
        const listDiv = document.getElementById('reset-selected-list');

        if (modal) {
            const selectedGroups = getSelectedGroups();
            if (selectedGroups.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('No groups selected', 'warning');
                }
                return;
            }

            if (countSpan) {
                countSpan.textContent = selectedGroups.length;
            }

            if (listDiv) {
                listDiv.innerHTML = selectedGroups.map(g =>
                    `<div class="py-1 border-b border-gray-200 dark:border-gray-700 last:border-0">${escapeHtml(g.name)}</div>`
                ).join('');
            }

            modal.classList.remove('hidden');
        }
    };

    window.hideResetSelectedModal = function() {
        const modal = document.getElementById('resetSelectedModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Get selected groups
    window.getSelectedGroups = function() {
        const checkboxes = document.querySelectorAll('.group-checkbox:checked');
        const groups = [];
        checkboxes.forEach(cb => {
            groups.push({
                id: cb.dataset.groupId,
                name: cb.dataset.groupName
            });
        });
        return groups;
    };

    // Toggle select all groups
    window.toggleSelectAllGroups = function(checkbox) {
        const isChecked = checkbox.checked;
        const groupCheckboxes = document.querySelectorAll('.group-checkbox');
        groupCheckboxes.forEach(cb => {
            cb.checked = isChecked;
        });
        updateSelectionUI();
    };

    // Update selection UI (counter, button visibility)
    window.updateSelectionUI = function() {
        const selectedGroups = getSelectedGroups();
        const count = selectedGroups.length;
        const counter = document.getElementById('selection-counter');
        const countSpan = document.getElementById('selected-count');
        const resetSelectedBtn = document.getElementById('reset-selected-btn');
        const selectAllCheckbox = document.getElementById('select-all-groups');
        const allCheckboxes = document.querySelectorAll('.group-checkbox');

        if (counter && countSpan) {
            if (count > 0) {
                counter.classList.remove('hidden');
                countSpan.textContent = count;
            } else {
                counter.classList.add('hidden');
            }
        }

        if (resetSelectedBtn) {
            if (count > 0) {
                resetSelectedBtn.classList.remove('hidden');
            } else {
                resetSelectedBtn.classList.add('hidden');
            }
        }

        // Update select-all checkbox state
        if (selectAllCheckbox && allCheckboxes.length > 0) {
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
    };

    // Reset selected groups
    window.ajax_group_reset_selected = function() {
        hideResetSelectedModal();

        const selectedGroups = getSelectedGroups();
        if (selectedGroups.length === 0) {
            if (typeof showToast === 'function') {
                showToast('No groups selected', 'warning');
            }
            return;
        }

        const groupIds = selectedGroups.map(g => g.id);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'reset_selected_groups',
                group_ids: JSON.stringify(groupIds)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || `${selectedGroups.length} group(s) reset successfully`, 'success');
                }
                // Clear selection
                document.querySelectorAll('.group-checkbox:checked').forEach(cb => {
                    cb.checked = false;
                });
                document.getElementById('select-all-groups').checked = false;
                updateSelectionUI();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error resetting selected groups', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('Error resetting selected groups', 'error');
            }
        });
    };

    // Initialize checkbox change listeners
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('group-checkbox')) {
            updateSelectionUI();
        }
    });
}

export { initAdminGroups };
