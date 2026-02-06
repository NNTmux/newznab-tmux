// Event delegation for dynamically added elements - optimized with early returns
export function initEventDelegation() {
    // Helper function to find element with attribute, checking both target and closest
    function findElementWithAttr(e, attr) {
        if (!e || !e.target) return null;
        if (e.target.hasAttribute(attr)) return e.target;
        return e.target.closest(`[${attr}]`);
    }

    // Helper function to find element with class
    function findElementWithClass(e, className) {
        if (!e || !e.target) return null;
        if (e.target.classList && e.target.classList.contains(className)) return e.target;
        return e.target.closest(`.${className}`);
    }

    document.addEventListener('click', function(e) {
        // Handle toast close buttons
        const toastClose = findElementWithClass(e, 'toast-close');
        if (toastClose) {
            const toast = toastClose.closest('.toast-notification');
            if (toast) {
                toast.remove();
            }
            return;
        }

        // Handle NFO modal close
        if (findElementWithAttr(e, 'data-close-nfo-modal')) {
            e.preventDefault();
            closeNfoModal();
            return;
        }

        // Handle NFO modal open
        const nfoOpen = findElementWithAttr(e, 'data-open-nfo');
        if (nfoOpen) {
            e.preventDefault();
            const guid = nfoOpen.getAttribute('data-open-nfo');
            if (guid) {
                openNfoModal(guid);
            }
            return;
        }

        // Handle logout
        if (findElementWithAttr(e, 'data-logout')) {
            e.preventDefault();
            const logoutForm = document.getElementById('logout-form');
            if (logoutForm) {
                logoutForm.submit();
            }
            return;
        }

        // Handle confirm delete - using styled modal
        const confirmDelete = findElementWithAttr(e, 'data-confirm-delete');
        if (confirmDelete) {
            e.preventDefault();
            e.stopPropagation();
            const form = confirmDelete.closest('form');

            showConfirm({
                message: 'Are you sure you want to delete this item?',
                type: 'danger',
                confirmText: 'Delete',
                onConfirm: function() {
                    if (form) {
                        form.submit();
                    } else if (confirmDelete.href) {
                        window.location.href = confirmDelete.href;
                    }
                }
            });
            return;
        }

        // Handle confirm action - using styled modal
        const confirmAction = findElementWithAttr(e, 'data-confirm');
        if (confirmAction) {
            e.preventDefault();
            e.stopPropagation();
            const message = confirmAction.getAttribute('data-confirm');
            const form = confirmAction.closest('form');

            showConfirm({
                message: message,
                type: 'danger',
                confirmText: 'Delete',
                onConfirm: function() {
                    if (form) {
                        form.submit();
                    } else if (confirmAction.href) {
                        window.location.href = confirmAction.href;
                    }
                }
            });
            return;
        }

        // Handle download NZB buttons
        if (e.target.classList.contains('download-nzb') || e.target.closest('.download-nzb')) {
            if (typeof showToast === 'function') {
                showToast('Downloading NZB...', 'success');
            }
        }

        // Handle season switcher - only for season-tab buttons, not content
        const seasonTab = e.target.classList.contains('season-tab') ? e.target : e.target.closest('.season-tab');
        if (seasonTab) {
            e.preventDefault();
            const seasonNumber = seasonTab.getAttribute('data-season');
            if (seasonNumber && typeof switchSeason === 'function') {
                switchSeason(seasonNumber);
            }
            return;
        }

        // Handle binary blacklist delete
        if (e.target.hasAttribute('data-delete-blacklist') || e.target.closest('[data-delete-blacklist]')) {
            const id = e.target.getAttribute('data-delete-blacklist') || e.target.closest('[data-delete-blacklist]').getAttribute('data-delete-blacklist');
            const confirmed = confirm('Are you sure? This will delete the blacklist from this list.');
            if (confirmed && typeof ajax_binaryblacklist_delete === 'function') {
                ajax_binaryblacklist_delete(id);
            }
            e.preventDefault();
        }

        // Handle confirmation modal close button
        if (e.target.hasAttribute('data-close-confirmation-modal') || e.target.closest('[data-close-confirmation-modal]')) {
            e.preventDefault();
            closeConfirmationModal();
        }

        // Handle confirmation modal confirm button
        if (e.target.hasAttribute('data-confirm-confirmation-modal') || e.target.closest('[data-confirm-confirmation-modal]')) {
            e.preventDefault();
            confirmConfirmationModal();
        }

        // Handle admin groups management actions
        const actionTarget = e.target.closest('[data-action]');
        if (actionTarget) {
            const action = actionTarget.dataset.action;
            const groupId = actionTarget.dataset.groupId;
            const status = actionTarget.dataset.status;

            switch(action) {
                case 'show-reset-modal':
                    showResetAllModal();
                    break;
                case 'hide-reset-modal':
                    hideResetAllModal();
                    break;
                case 'show-purge-modal':
                    showPurgeAllModal();
                    break;
                case 'hide-purge-modal':
                    hidePurgeAllModal();
                    break;
                case 'show-reset-selected-modal':
                    showResetSelectedModal();
                    break;
                case 'hide-reset-selected-modal':
                    hideResetSelectedModal();
                    break;
                case 'select-all-groups':
                    toggleSelectAllGroups(actionTarget);
                    break;
                case 'toggle-group-status':
                    ajax_group_status(groupId, status);
                    break;
                case 'toggle-backfill':
                    ajax_backfill_status(groupId, status);
                    break;
                case 'reset-group':
                    ajax_group_reset(groupId);
                    break;
                case 'delete-group':
                    confirmGroupDelete(groupId);
                    break;
                case 'purge-group':
                    confirmGroupPurge(groupId);
                    break;
                case 'reset-all':
                    ajax_group_reset_all();
                    break;
                case 'purge-all':
                    ajax_group_purge_all();
                    break;
                case 'reset-selected':
                    ajax_group_reset_selected();
                    break;
            }
        }

        // Handle restore user button (both admin/user-list and admin/deleted-users pages)
        const restoreUserBtn = e.target.closest('.restore-user-btn');
        if (restoreUserBtn) {
            e.preventDefault();
            const userId = restoreUserBtn.getAttribute('data-user-id');
            const username = restoreUserBtn.getAttribute('data-username') || 'this user';

            showConfirm({
                title: 'Restore User',
                message: `Are you sure you want to restore user "${username}"?`,
                type: 'success',
                confirmText: 'Restore',
                cancelText: 'Cancel',
                onConfirm: function() {
                    const form = document.getElementById('individualActionForm');
                    if (!form) {
                        console.error('Individual action form not found');
                        if (typeof showToast === 'function') {
                            showToast('Error: Form not found', 'error');
                        }
                        return;
                    }

                    // Set form action and submit
                    form.action = `/admin/deleted-users/restore/${userId}`;
                    form.method = 'POST';
                    form.submit();
                }
            });
            return;
        }

        // Handle permanent delete user button (deleted users page only)
        const deleteUserBtn = e.target.closest('.delete-user-btn');
        if (deleteUserBtn) {
            e.preventDefault();
            const userId = deleteUserBtn.getAttribute('data-user-id');
            const username = deleteUserBtn.getAttribute('data-username') || 'this user';

            showConfirm({
                title: 'Permanently Delete User',
                message: `Are you sure you want to permanently delete user "${username}"? This action cannot be undone and will remove all user data.`,
                type: 'danger',
                confirmText: 'Delete Permanently',
                cancelText: 'Cancel',
                onConfirm: function() {
                    const form = document.getElementById('individualActionForm');
                    if (!form) {
                        console.error('Individual action form not found');
                        if (typeof showToast === 'function') {
                            showToast('Error: Form not found', 'error');
                        }
                        return;
                    }

                    // Set form action and submit
                    form.action = `/admin/deleted-users/permanent-delete/${userId}`;
                    form.method = 'POST';
                    form.submit();
                }
            });
            return;
        }

        // Handle promotion toggle (activate/deactivate)
        const promotionToggleBtn = e.target.closest('.promotion-toggle-btn');
        if (promotionToggleBtn) {
            e.preventDefault();
            const promotionName = promotionToggleBtn.getAttribute('data-promotion-name');
            const isActive = promotionToggleBtn.getAttribute('data-promotion-active') === '1';
            const action = isActive ? 'deactivate' : 'activate';

            showConfirm({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Promotion`,
                message: `Are you sure you want to ${action} the promotion "${promotionName}"?`,
                type: isActive ? 'warning' : 'success',
                confirmText: action.charAt(0).toUpperCase() + action.slice(1),
                cancelText: 'Cancel',
                onConfirm: function() {
                    window.location.href = promotionToggleBtn.href;
                }
            });
            return;
        }

        // Handle promotion delete
        const promotionDeleteBtn = e.target.closest('.promotion-delete-btn');
        if (promotionDeleteBtn) {
            e.preventDefault();
            const promotionName = promotionDeleteBtn.getAttribute('data-promotion-name');
            const form = promotionDeleteBtn.closest('form');

            showConfirm({
                title: 'Delete Promotion',
                message: `Are you sure you want to delete the promotion "${promotionName}"?`,
                details: 'This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                onConfirm: function() {
                    if (form) {
                        form.submit();
                    }
                }
            });
            return;
        }
    });

    // Handle select redirects
    document.addEventListener('change', function(e) {
        if (e.target.hasAttribute('data-redirect-on-change')) {
            const url = e.target.value;
            if (url && url !== '#') {
                window.location.href = url;
            }
        }
    });
}
