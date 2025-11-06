// CSP-Safe JavaScript - All inline event handlers moved here
// This file handles all onclick, onchange, and other inline event handlers

// Utility function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

document.addEventListener('DOMContentLoaded', function() {
    initEventDelegation();
    initToastNotifications();
    initNfoModal();
    initConfirmDialogs();
    initSelectRedirects();
    initLogoutForms();
    initSeasonSwitcher();
    initBinaryBlacklist();
    initImageModal();
    initTabSwitcher();
    initPreviewModal();
    initRegexManagement();
    initMediainfoAndFilelist();
    initCartFunctionality();
    initAdminMenu();
    initSidebarToggle();
    initDropdownMenus();
    initImageFallbacks();
    initProfileTabs();
    initAdminUserEdit();
    initMobileEnhancements();
    initAdminDashboardCharts();
    initAdminGroups();
    initTinyMCE();
    initAdminSpecificFeatures();
    initRecentActivityRefresh();

    // Initialize page-specific functionality from inline scripts
    initMyMovies();
    initAuthPages();
    initProfileEdit();
    initDetailsPageImageModal();
    initMoviesLayoutToggle();
    initProfilePage(); // Initialize profile page (progress bars and charts)
    initCopyToClipboard(); // Copy functionality for RSS/Profile pages
    initVerifyUserModal(); // Admin user verification modal
});

// Event delegation for dynamically added elements - optimized with early returns
function initEventDelegation() {
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

// Toast Notifications
function initToastNotifications() {
    // showToast is defined globally for backward compatibility
    window.showToast = function(message, type) {
        type = type || 'success';

        let container = document.getElementById('toast-container');
        if (!container) {
            // Create container if it doesn't exist
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + type;

        const iconClass = type === 'success' ? 'fa-check-circle' :
                        type === 'error' ? 'fa-exclamation-circle' :
                        'fa-info-circle';

        toast.innerHTML =
            '<span class="toast-icon"><i class="fas ' + iconClass + '"></i></span>' +
            '<span class="toast-message">' + escapeHtml(message) + '</span>' +
            '<button class="toast-close" type="button" aria-label="Close">×</button>';

        container.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            toast.classList.add('removing');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 5000);

        // Add close button listener
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                toast.classList.add('removing');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            });
        }
    };
}

// Styled Confirmation Modal (replaces native confirm dialogs)
let confirmationCallback = null;

window.showConfirm = function(options) {
    // Options: { message, title, details, confirmText, cancelText, type, onConfirm }
    const modal = document.getElementById('confirmationModal');
    if (!modal) return Promise.reject('Modal not found');

    const defaults = {
        title: 'Confirm Action',
        message: 'Are you sure you want to proceed?',
        details: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        type: 'info', // 'info', 'warning', 'danger', 'success'
        onConfirm: null
    };

    const config = { ...defaults, ...options };

    // Set modal content - with null checks
    const titleText = document.getElementById('confirmationModalTitleText');
    const messageText = document.getElementById('confirmationModalMessage');
    const confirmText = document.getElementById('confirmationModalConfirmText');
    const cancelText = document.getElementById('confirmationModalCancelText');

    if (titleText) titleText.textContent = config.title;
    if (messageText) messageText.textContent = config.message;
    if (confirmText) confirmText.textContent = config.confirmText;
    if (cancelText) cancelText.textContent = config.cancelText;

    // Set icon based on type
    const icon = document.getElementById('confirmationModalIcon');
    const confirmBtn = document.getElementById('confirmationModalConfirmBtn');

    // Helper function to set classes (works with both regular elements and SVG)
    function setIconClasses(element, classes) {
        if (!element) return;
        if (element instanceof SVGElement) {
            element.setAttribute('class', classes.join(' '));
        } else {
            element.className = classes.join(' ');
        }
    }

    // Set base icon classes
    let iconClasses = ['fas', 'mr-2'];

    // Remove all existing classes from button and set base classes
    if (confirmBtn) {
        confirmBtn.className = '';
        confirmBtn.classList.add('px-4', 'py-2', 'text-white', 'rounded-lg', 'transition', 'font-medium');
    }

    if (config.type === 'danger') {
        iconClasses.push('fa-exclamation-triangle', 'text-red-600', 'dark:text-red-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-red-600', 'dark:bg-red-700', 'hover:bg-red-700', 'dark:hover:bg-red-800');
        }
    } else if (config.type === 'warning') {
        iconClasses.push('fa-exclamation-circle', 'text-yellow-600', 'dark:text-yellow-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-yellow-600', 'dark:bg-yellow-700', 'hover:bg-yellow-700', 'dark:hover:bg-yellow-800');
        }
    } else if (config.type === 'success') {
        iconClasses.push('fa-check-circle', 'text-green-600', 'dark:text-green-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-green-600', 'dark:bg-green-700', 'hover:bg-green-700', 'dark:hover:bg-green-800');
        }
    } else { // info
        iconClasses.push('fa-info-circle', 'text-blue-600', 'dark:text-blue-400');
        if (confirmBtn) {
            confirmBtn.classList.add('bg-blue-600', 'dark:bg-blue-700', 'hover:bg-blue-700', 'dark:hover:bg-blue-800');
        }
    }

    // Apply icon classes
    setIconClasses(icon, iconClasses);

    // Handle details
    const detailsDiv = document.getElementById('confirmationModalDetails');
    const detailsText = document.getElementById('confirmationModalDetailsText');
    if (config.details && detailsText && detailsDiv) {
        detailsText.textContent = config.details;
        detailsDiv.classList.remove('hidden');
    } else if (detailsDiv) {
        detailsDiv.classList.add('hidden');
    }

    // Store callback
    confirmationCallback = config.onConfirm;

    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Return promise for async/await usage
    return new Promise((resolve, reject) => {
        confirmationCallback = function(confirmed) {
            if (confirmed && config.onConfirm) {
                config.onConfirm();
            }
            resolve(confirmed);
        };
    });
};

window.closeConfirmationModal = function() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    if (confirmationCallback) {
        confirmationCallback(false);
        confirmationCallback = null;
    }
};

window.confirmConfirmationModal = function() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    if (confirmationCallback) {
        confirmationCallback(true);
        confirmationCallback = null;
    }
};

// Enhanced confirm function (backward compatible but with styled modal)
window.confirmStyled = function(message, title = 'Confirm', type = 'info') {
    return new Promise((resolve) => {
        showConfirm({
            message: message,
            title: title,
            type: type,
            onConfirm: () => resolve(true)
        }).then(result => {
            if (!result) resolve(false);
        });
    });
};

// NFO Modal
function initNfoModal() {
    window.openNfoModal = function(guid) {
        const modal = document.getElementById('nfoModal');
        const content = document.getElementById('nfoContent');

        if (!modal || !content) return;

        // Show modal
        modal.classList.remove('hidden');
        modal.style.display = 'block';

        // Reset content with loading message
        content.innerHTML = '<div class="flex items-center justify-center py-8"><i class="fas fa-spinner fa-spin text-2xl mr-2"></i><span>Loading NFO...</span></div>';

        // Fetch NFO content
        const baseUrl = document.querySelector('meta[name="app-url"]')?.content || '';
        fetch(baseUrl + '/nfo/' + guid + '?modal=1')
            .then(response => {
                if (!response.ok) {
                    throw new Error('NFO not found');
                }
                return response.text();
            })
            .then(html => {
                // Extract the NFO content from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nfoText = doc.querySelector('pre')?.textContent || doc.body.textContent;
                content.textContent = nfoText;
            })
            .catch(error => {
                content.innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-triangle text-2xl mr-2"></i><span>Error loading NFO file</span></div>';
                console.error('Error loading NFO:', error);
            });
    };

    window.closeNfoModal = function() {
        const modal = document.getElementById('nfoModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    };

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('nfoModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeNfoModal();
            }
        }
    });

    // Add click handlers for NFO badges
    document.addEventListener('click', function(event) {
        if (event.target.closest('.nfo-badge')) {
            event.preventDefault();
            const badge = event.target.closest('.nfo-badge');
            const guid = badge.getAttribute('data-guid');
            if (guid) {
                openNfoModal(guid);
            }
        }
    });
}

// Confirm dialogs
function initConfirmDialogs() {
    window.confirmDelete = function(id) {
        if (confirm('Are you sure you want to delete this item?')) {
            // If there's a form with this ID, submit it
            const form = document.getElementById('delete-form-' + id);
            if (form) {
                form.submit();
            }
            return true;
        }
        return false;
    };
}

// Select redirects
function initSelectRedirects() {
    // Already handled in event delegation
}

// Logout forms
function initLogoutForms() {
    // Already handled in event delegation
}

// Season switcher for series - optimized
function initSeasonSwitcher() {
    window.switchSeason = function(seasonNumber) {
        // Hide all season content
        document.querySelectorAll('.season-content').forEach(function(content) {
            content.classList.add('hidden');
        });

        // Remove active styling from all tabs
        document.querySelectorAll('.season-tab').forEach(function(tab) {
            tab.classList.remove('border-blue-500', 'text-blue-600');
            tab.classList.add('border-transparent', 'text-gray-500');

            // Update badge styling
            const badge = tab.querySelector('span');
            if (badge) {
                badge.classList.remove('bg-blue-100', 'text-blue-800');
                badge.classList.add('bg-gray-100', 'text-gray-600');
            }
        });

        // Show selected season content
        const selectedContent = document.querySelector('.season-content[data-season="' + seasonNumber + '"]');
        if (selectedContent) {
            selectedContent.classList.remove('hidden');
        }

        // Add active styling to selected tab
        const selectedTab = document.querySelector('.season-tab[data-season="' + seasonNumber + '"]');
        if (selectedTab) {
            selectedTab.classList.remove('border-transparent', 'text-gray-500');
            selectedTab.classList.add('border-blue-500', 'text-blue-600');

            // Update badge styling
            const badge = selectedTab.querySelector('span');
            if (badge) {
                badge.classList.remove('bg-gray-100', 'text-gray-600');
                badge.classList.add('bg-blue-100', 'text-blue-800');
            }
        }
    };
}

// Binary blacklist
function initBinaryBlacklist() {
    // Placeholder - actual implementation depends on existing ajax function
    window.ajax_binaryblacklist_delete = window.ajax_binaryblacklist_delete || function(id) {
        console.log('Delete blacklist:', id);
    };
}

// Image modal
function initImageModal() {
    // Handle image modal if needed
    document.querySelectorAll('[data-open-image-modal]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const imageUrl = this.getAttribute('data-open-image-modal');
            openImageModal(imageUrl);
        });
    });

    window.openImageModal = function(imageUrl) {
        const modal = document.getElementById('imageModal');
        if (modal) {
            const img = modal.querySelector('img');
            if (img) {
                img.src = imageUrl;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    };

    window.closeImageModal = function() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };
}

// Tab switcher for profile and other pages
function initTabSwitcher() {
    document.querySelectorAll('[data-tab-trigger]').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab-trigger');

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.style.display = 'none';
            });

            // Show selected tab
            const selectedTab = document.getElementById(tabId);
            if (selectedTab) {
                selectedTab.style.display = 'block';
            }

            // Update active state
            document.querySelectorAll('[data-tab-trigger]').forEach(function(t) {
                t.classList.remove('active', 'border-blue-500', 'text-blue-600');
                t.classList.add('border-transparent', 'text-gray-500');
            });

            this.classList.remove('border-transparent', 'text-gray-500');
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
        });
    });
}

// Preview Modal for Browse Page
function initPreviewModal() {
    window.closePreviewModal = function() {
        const modal = document.getElementById('previewModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            const img = document.getElementById('previewImage');
            if (img) img.src = '';
        }
    };

    window.showPreviewImage = function(guid, type = 'preview') {
        const modal = document.getElementById('previewModal');
        const img = document.getElementById('previewImage');
        const error = document.getElementById('previewError');
        const title = document.getElementById('previewTitle');

        if (!modal || !img || !error || !title) return;

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        title.textContent = type === 'sample' ? 'Sample Image' : 'Preview Image';

        const imageUrl = '/covers/' + type + '/' + guid + '_thumb.jpg';
        img.src = imageUrl;
        error.classList.add('hidden');
        img.style.display = 'block';

        img.onerror = function() {
            error.textContent = (type === 'sample' ? 'Sample' : 'Preview') + ' image not available';
            error.classList.remove('hidden');
            img.style.display = 'none';
        };

        img.onload = function() {
            img.style.display = 'block';
        };
    };

    // Event listeners for preview badges
    document.querySelectorAll('.preview-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            const guid = this.getAttribute('data-guid');
            if (guid) {
                window.showPreviewImage(guid, 'preview');
            }
        });
    });

    // Event listeners for sample badges
    document.querySelectorAll('.sample-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            const guid = this.getAttribute('data-guid');
            if (guid) {
                window.showPreviewImage(guid, 'sample');
            }
        });
    });

    // Event listeners for NFO badges
    document.querySelectorAll('.nfo-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            const guid = this.getAttribute('data-guid');
            if (guid) {
                fetch('/getnfo/' + guid)
                    .then(response => response.text())
                    .then(data => {
                        const nfoContent = document.getElementById('nfo-content');
                        if (nfoContent) {
                            nfoContent.textContent = data;
                            const nfoModal = document.getElementById('nfo-modal');
                            if (nfoModal) {
                                nfoModal.classList.remove('hidden');
                                nfoModal.classList.add('flex');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching NFO:', error);
                    });
            }
        });
    });

    // Close preview modal on background click
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                window.closePreviewModal();
            }
        });

        // Event listener for close button (using onclick attribute)
        const closeButton = previewModal.querySelector('button[onclick*="closePreviewModal"]');
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation();
                window.closePreviewModal();
            });
        }

        // Also handle any data-close-preview buttons
        const dataCloseButton = previewModal.querySelector('[data-close-preview]');
        if (dataCloseButton) {
            dataCloseButton.addEventListener('click', function(e) {
                e.stopPropagation();
                window.closePreviewModal();
            });
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closePreviewModal();
        }
    });
}

// Regex Management Functions
function initRegexManagement() {
    window.deleteRegex = function(id, deleteUrl) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        fetch(deleteUrl + '?id=' + id, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('row-' + id);
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
                showMessage('Regex deleted successfully', 'success');
            } else {
                showMessage('Error deleting regex', 'error');
            }
        })
        .catch(error => {
            showMessage('Error deleting regex', 'error');
            console.error('Error:', error);
        });
    };

    window.showMessage = function(message, type = 'success') {
        const messageDiv = document.getElementById('message');
        if (!messageDiv) return;

        const bgColor = type === 'success' ? 'bg-green-50 dark:bg-green-900 border-green-200 dark:border-green-700' : 'bg-red-50 dark:bg-red-900 border-red-200 dark:border-red-700';
        const textColor = type === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';

        const messageEl = document.createElement('div');
        messageEl.className = 'mt-4 p-4 border rounded-lg ' + bgColor;
        messageEl.innerHTML = `
            <div class="flex items-center justify-between">
                <p class="${textColor}">
                    <i class="fa fa-${icon} mr-2"></i>${escapeHtml(message)}
                </p>
                <button type="button" class="${textColor} hover:opacity-75 close-message">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        `;

        messageDiv.appendChild(messageEl);

        // Add close handler
        messageEl.querySelector('.close-message').addEventListener('click', function() {
            messageEl.remove();
        });

        // Auto-remove after 5 seconds
        setTimeout(() => {
            messageEl.remove();
        }, 5000);
    };

    // Handle delete buttons with proper confirmation
    document.addEventListener('click', function(e) {
        if (e.target.hasAttribute('data-delete-regex') || e.target.closest('[data-delete-regex]')) {
            const element = e.target.hasAttribute('data-delete-regex') ? e.target : e.target.closest('[data-delete-regex]');
            const id = element.getAttribute('data-delete-regex');
            const deleteUrl = element.getAttribute('data-delete-url');

            if (confirm('Are you sure you want to delete this regex? This action cannot be undone.')) {
                deleteRegex(id, deleteUrl);
            }
            e.preventDefault();
        }
    });
}

// Mediainfo and Filelist Modals
function initMediainfoAndFilelist() {
    window.closeMediainfoModal = function() {
        const modal = document.getElementById('mediainfoModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.closeFilelistModal = function() {
        const modal = document.getElementById('filelistModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    window.showFilelist = function(guid) {
        let modal = document.getElementById('filelistModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const content = document.getElementById('filelistContent');
        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.zIndex = '99999';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-green-600"></i>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Loading file list...</p>
            </div>
        `;

        const apiUrl = '/api/release/' + guid + '/filelist';

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load file list');
                }
                return response.json();
            })
            .then(data => {
                if (!data.files || data.files.length === 0) {
                    content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No files available</p>';
                    return;
                }

                let html = '<div class="space-y-4">';
                html += `
                    <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">${escapeHtml(data.release.searchname)}</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Files: ${data.total}</p>
                    </div>
                `;

                html += `
                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">File Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                `;

                data.files.forEach((file, index) => {
                    const rowClass = index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900';
                    const fileName = file.title || file.name || 'Unknown';
                    const fileSize = file.size ? formatFileSize(file.size) : 'N/A';

                    html += `
                        <tr class="${rowClass} hover:bg-gray-100 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 break-all">${escapeHtml(fileName)}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">${fileSize}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                html += '</div>';
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                        <p class="text-red-600 mt-2">${escapeHtml(error.message)}</p>
                    </div>
                `;
            });
    };

    window.showMediainfo = function(releaseId) {
        let modal = document.getElementById('mediainfoModal');

        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const content = document.getElementById('mediainfoContent');

        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.zIndex = '99999';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';

        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Loading media information...</p>
            </div>
        `;

        const apiUrl = '/api/release/' + releaseId + '/mediainfo';

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load media information');
                }
                return response.json();
            })
            .then(data => {
                if (!data.video && !data.audio && !data.subs) {
                    content.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No media information available</p>';
                    return;
                }

                let html = '<div class="space-y-6">';

                // Video information
                if (data.video) {
                    html += `
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-video mr-2 text-blue-600 dark:text-blue-400"></i> Video Information
                            </h4>
                            <dl class="grid grid-cols-2 gap-3">
                    `;

                    if (data.video.containerformat) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Container</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.containerformat)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videocodec) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Codec</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videocodec)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videowidth && data.video.videoheight) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Resolution</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${data.video.videowidth}x${data.video.videoheight}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoaspect) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Aspect Ratio</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videoaspect)}</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoframerate) {
                        html += `
                            <div>
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Frame Rate</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.video.videoframerate)} fps</dd>
                            </div>
                        `;
                    }
                    if (data.video.videoduration) {
                        const durationMs = parseInt(data.video.videoduration);
                        if (!isNaN(durationMs) && durationMs > 0) {
                            const minutes = Math.round(durationMs / 1000 / 60);
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Duration</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${minutes} minutes</dd>
                                </div>
                            `;
                        }
                    }

                    html += '</dl></div>';
                }

                // Audio information
                if (data.audio && data.audio.length > 0) {
                    html += `
                        <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900 dark:to-teal-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-volume-up mr-2 text-green-600 dark:text-green-400"></i> Audio Information
                            </h4>
                    `;

                    data.audio.forEach((audio, index) => {
                        if (index > 0) html += '<hr class="my-3 border-gray-200 dark:border-gray-700">';
                        html += '<dl class="grid grid-cols-2 gap-3">';

                        if (audio.audioformat) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Format</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audioformat)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiochannels) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Channels</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiochannels)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiobitrate) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Bit Rate</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiobitrate)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiolanguage) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Language</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiolanguage)}</dd>
                                </div>
                            `;
                        }
                        if (audio.audiosamplerate) {
                            html += `
                                <div>
                                    <dt class="text-xs font-medium text-gray-600 dark:text-gray-400">Sample Rate</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(audio.audiosamplerate)}</dd>
                                </div>
                            `;
                        }

                        html += '</dl>';
                    });

                    html += '</div>';
                }

                // Subtitle information
                if (data.subs) {
                    html += `
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                                <i class="fas fa-closed-captioning mr-2 text-purple-600 dark:text-purple-400"></i> Subtitles
                            </h4>
                            <p class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(data.subs)}</p>
                        </div>
                    `;
                }

                html += '</div>';
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-circle text-3xl text-red-600"></i>
                        <p class="text-red-600 mt-2">${escapeHtml(error.message)}</p>
                    </div>
                `;
            });
    };

    // Event handlers for badges and modals
    document.addEventListener('click', function(e) {
        // Preview badge
        const previewBadge = e.target.closest('.preview-badge');
        if (previewBadge) {
            e.preventDefault();
            const guid = previewBadge.dataset.guid;
            if (typeof showPreviewImage === 'function') {
                showPreviewImage(guid, 'preview');
            }
        }

        // Sample badge
        const sampleBadge = e.target.closest('.sample-badge');
        if (sampleBadge) {
            e.preventDefault();
            const guid = sampleBadge.dataset.guid;
            if (typeof showPreviewImage === 'function') {
                showPreviewImage(guid, 'sample');
            }
        }

        // Mediainfo badge
        const mediainfoBadge = e.target.closest('.mediainfo-badge');
        if (mediainfoBadge) {
            e.preventDefault();
            const releaseId = mediainfoBadge.dataset.releaseId;
            showMediainfo(releaseId);
        }

        // File list badge
        const filelistBadge = e.target.closest('.filelist-badge');
        if (filelistBadge) {
            e.preventDefault();
            const guid = filelistBadge.dataset.guid;
            showFilelist(guid);
        }

        // Handle modal close buttons with data attributes
        if (e.target.hasAttribute('data-close-preview-modal') || e.target.closest('[data-close-preview-modal]')) {
            e.preventDefault();
            if (typeof closePreviewModal === 'function') {
                closePreviewModal();
            }
        }

        if (e.target.hasAttribute('data-close-mediainfo-modal') || e.target.closest('[data-close-mediainfo-modal]')) {
            e.preventDefault();
            if (typeof closeMediainfoModal === 'function') {
                closeMediainfoModal();
            }
        }

        if (e.target.hasAttribute('data-close-filelist-modal') || e.target.closest('[data-close-filelist-modal]')) {
            e.preventDefault();
            if (typeof closeFilelistModal === 'function') {
                closeFilelistModal();
            }
        }
    });

    // Close modals on background click
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this && typeof closePreviewModal === 'function') {
                closePreviewModal();
            }
        });
    }

    const mediainfoModal = document.getElementById('mediainfoModal');
    if (mediainfoModal) {
        mediainfoModal.addEventListener('click', function(e) {
            // Close if clicking the backdrop OR the close button
            if (e.target === this || e.target.closest('[data-close-mediainfo-modal]')) {
                closeMediainfoModal();
            }
        });
    }

    const filelistModal = document.getElementById('filelistModal');
    if (filelistModal) {
        filelistModal.addEventListener('click', function(e) {
            // Close if clicking the backdrop OR the close button
            if (e.target === this || e.target.closest('[data-close-filelist-modal]')) {
                closeFilelistModal();
            }
        });
    }

    // Close modals on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (typeof closePreviewModal === 'function') closePreviewModal();
            closeMediainfoModal();
            closeFilelistModal();
        }
    });
}

// Cart and Multi-select functionality
function initCartFunctionality() {
    // Handle individual add to cart button clicks (for details page, browse page, etc.)
    document.addEventListener('click', function(e) {
        const cartBtn = e.target.closest('.add-to-cart');

        if (cartBtn) {
            e.preventDefault();

            const guid = cartBtn.dataset.guid;
            const iconElement = cartBtn.querySelector('.icon_cart');

            if (!guid) {
                console.error('No GUID found for cart item');
                return;
            }

            // Prevent double-clicking
            if (iconElement && iconElement.classList.contains('icon_cart_clicked')) {
                return;
            }

            // Send AJAX request to add item to cart
            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: guid })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Visual feedback
                    if (iconElement) {
                        iconElement.classList.remove('fa-shopping-basket');
                        iconElement.classList.add('fa-check', 'icon_cart_clicked');

                        // Reset icon after 2 seconds
                        setTimeout(() => {
                            iconElement.classList.remove('fa-check', 'icon_cart_clicked');
                            iconElement.classList.add('fa-shopping-basket');
                        }, 2000);
                    }

                    // Update cart count if element exists
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount && data.cartCount) {
                        cartCount.textContent = data.cartCount;
                    }

                    // Show success notification
                    if (typeof showToast === 'function') {
                        showToast('Added to cart successfully!', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Failed to add item to cart', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                if (typeof showToast === 'function') {
                    showToast('An error occurred', 'error');
                }
            });
        }
    });

    // Cart page specific functionality
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cart-checkbox');

    if (checkAll && checkboxes.length > 0) {
        // Function to update the check-all checkbox state
        function updateCheckAllState() {
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            checkAll.checked = checkedCount === checkboxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        // Handle check-all checkbox change
        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });

        // Handle individual checkbox changes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCheckAllState);
        });

        // Initialize the check-all state on page load
        updateCheckAllState();

        // Download selected
        document.querySelectorAll('.nzb_multi_operations_download_cart').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selected.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('Please select at least one item', 'error');
                    } else {
                        alert('Please select at least one item');
                    }
                    return;
                }

                // Download all selected NZBs
                selected.forEach(guid => {
                    window.open('/getnzb?id=' + guid, '_blank');
                });

                if (typeof showToast === 'function') {
                    showToast('Downloading ' + selected.length + ' item(s)', 'success');
                }
            });
        });

        // Delete selected
        document.querySelectorAll('.nzb_multi_operations_cartdelete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selected.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('Please select at least one item', 'error');
                    } else {
                        alert('Please select at least one item');
                    }
                    return;
                }

                showConfirm({
                    title: 'Delete from Cart',
                    message: `Are you sure you want to delete ${selected.length} item${selected.length > 1 ? 's' : ''} from your cart?`,
                    type: 'danger',
                    confirmText: 'Delete',
                    onConfirm: function() {
                        // Delete via AJAX
                        fetch('/cart/delete/' + selected.join(','), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json',
                            }
                        })
                        .then(response => {
                            if (response.ok) {
                                if (typeof showToast === 'function') {
                                    showToast('Items deleted successfully', 'success');
                                }
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('Failed to delete items', 'error');
                                } else {
                                    alert('Failed to delete items');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            if (typeof showToast === 'function') {
                                showToast('Failed to delete items', 'error');
                            } else {
                                alert('Failed to delete items');
                            }
                        });
                    }
                });
            });
        });

        // Individual delete confirmation
        document.querySelectorAll('.cart-delete-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const releaseName = this.getAttribute('data-release-name');
                const deleteUrl = this.getAttribute('data-delete-url');

                showConfirm({
                    title: 'Remove from Cart',
                    message: `Are you sure you want to remove "${releaseName}" from your cart?`,
                    type: 'warning',
                    confirmText: 'Remove',
                    onConfirm: function() {
                        if (typeof showToast === 'function') {
                            showToast('Removing item from cart...', 'info');
                        }

                        setTimeout(() => {
                            window.location.href = deleteUrl;
                        }, 500);
                    }
                });
            });
        });
    }

    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('chkSelectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.chkRelease');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    }

    // Multi-operations download
    const multiDownloadBtn = document.querySelector('.nzb_multi_operations_download');
    if (multiDownloadBtn) {
        multiDownloadBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release', 'error');
                }
                return;
            }

            // If only one release is selected, download it directly
            if (selected.length === 1) {
                window.location.href = '/getnzb/' + selected[0];
                if (typeof showToast === 'function') {
                    showToast('Downloading NZB...', 'success');
                }
            } else {
                // For multiple releases, download as zip
                const guids = selected.join(',');
                window.location.href = '/getnzb?id=' + encodeURIComponent(guids) + '&zip=1';
                if (typeof showToast === 'function') {
                    showToast(`Downloading ${selected.length} NZBs as zip file...`, 'success');
                }
            }
        });
    }

    // Multi-operations cart
    const multiCartBtn = document.querySelector('.nzb_multi_operations_cart');
    if (multiCartBtn) {
        multiCartBtn.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.chkRelease:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release', 'error');
                }
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: selected.join(',') })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast(data.message || `Added ${selected.length} item${selected.length > 1 ? 's' : ''} to cart`, 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Failed to add items to cart', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                if (typeof showToast === 'function') {
                    showToast('An error occurred', 'error');
                }
            });
        });
    }

    // Multi-operations delete (Admin only)
    const multiDeleteBtn = document.querySelector('.nzb_multi_operations_delete');
    if (multiDeleteBtn) {
        console.log('Delete button found and event listener being attached');
        multiDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Delete button clicked');

            const checkboxes = document.querySelectorAll('.chkRelease:checked');
            const selected = Array.from(checkboxes);

            console.log('Selected releases:', selected.length);

            if (selected.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('Please select at least one release to delete', 'error');
                } else {
                    alert('Please select at least one release to delete');
                }
                return;
            }

            const confirmMessage = `Are you sure you want to delete ${selected.length} release${selected.length > 1 ? 's' : ''}? This action cannot be undone.`;

            // Use styled confirmation modal
            showConfirm({
                title: 'Delete Releases',
                message: confirmMessage,
                type: 'danger',
                confirmText: 'Delete',
                onConfirm: function() {
                console.log('User confirmed deletion');

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    if (typeof showToast === 'function') {
                        showToast('Security token not found. Please refresh the page.', 'error');
                    } else {
                        alert('Security token not found. Please refresh the page.');
                    }
                    return;
                }

                let deletedCount = 0;
                let errorCount = 0;

                if (typeof showToast === 'function') {
                    showToast(`Deleting ${selected.length} release${selected.length > 1 ? 's' : ''}...`, 'info');
                }

                // Delete releases one by one
                const deletePromises = selected.map(checkbox => {
                    const guid = checkbox.value;
                    const row = checkbox.closest('tr');

                    console.log('Attempting to delete release:', guid);

                    return fetch('/admin/release-delete/' + guid, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        console.log('Delete response status:', response.status, 'for guid:', guid);
                        if (response.ok) {
                            deletedCount++;
                            // Fade out and remove the row
                            if (row) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            }
                            return true;
                        } else {
                            errorCount++;
                            console.error('Failed to delete release:', guid, 'Status:', response.status);
                            return response.text().then(text => {
                                console.error('Error response:', text);
                                return false;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting release:', guid, error);
                        errorCount++;
                        return false;
                    });
                });

                // Wait for all deletes to complete
                Promise.all(deletePromises).then(() => {
                    console.log('All delete operations completed. Deleted:', deletedCount, 'Errors:', errorCount);

                    // Uncheck the select all checkbox
                    const selectAllCheckbox = document.getElementById('chkSelectAll');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    }

                    // Show final result
                    if (deletedCount > 0 && errorCount === 0) {
                        if (typeof showToast === 'function') {
                            showToast(`Successfully deleted ${deletedCount} release${deletedCount > 1 ? 's' : ''}`, 'success');
                        }
                    } else if (deletedCount > 0 && errorCount > 0) {
                        if (typeof showToast === 'function') {
                            showToast(`Deleted ${deletedCount} release${deletedCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('Failed to delete releases', 'error');
                        }
                    }

                    // Reload page if all items on current page were deleted
                    const remainingRows = document.querySelectorAll('.chkRelease');
                    if (remainingRows.length === 0) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                });
                }
            });
        });
    }
}

// Admin Menu Toggle
function initAdminMenu() {
    window.toggleAdminSubmenu = function(id) {
        const submenu = document.getElementById(id);
        const icon = document.getElementById(id + '-icon');
        if (submenu) {
            submenu.classList.toggle('hidden');
        }
        if (icon) {
            icon.classList.toggle('rotate-180');
        }
    };

    // Add click handlers for admin menu buttons
    document.addEventListener('click', function(e) {
        const menuButton = e.target.closest('[data-toggle-submenu]');
        if (menuButton) {
            const menuId = menuButton.getAttribute('data-toggle-submenu');
            if (menuId) {
                toggleAdminSubmenu(menuId);
            }
        }
    });

    // Theme management with system preference support for admin panel
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const themeToggle = document.getElementById('theme-toggle');

    if (themeToggle) {
        // Get initial theme preference
        const metaTheme = document.querySelector('meta[name="theme-preference"]');
        const isAuthenticated = document.querySelector('meta[name="user-authenticated"]');
        let currentTheme = metaTheme ? metaTheme.content : 'light';

        if (!isAuthenticated || isAuthenticated.content !== 'true') {
            currentTheme = localStorage.getItem('theme') || 'light';
        }

        // Function to apply theme
        function applyTheme(themePreference) {
            const html = document.documentElement;

            if (themePreference === 'system') {
                if (mediaQuery.matches) {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            } else if (themePreference === 'dark') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        }

        // Apply initial theme
        applyTheme(currentTheme);

        // Listen for OS theme changes
        mediaQuery.addEventListener('change', () => {
            const userThemePreference = metaTheme ? metaTheme.content : localStorage.getItem('theme') || 'light';
            if (userThemePreference === 'system') {
                applyTheme('system');
            }
        });

        // Theme toggle click handler - cycles through light -> dark -> system
        themeToggle.addEventListener('click', function() {
            let nextTheme;

            // Cycle through: light -> dark -> system -> light
            if (currentTheme === 'light') {
                nextTheme = 'dark';
            } else if (currentTheme === 'dark') {
                nextTheme = 'system';
            } else {
                nextTheme = 'light';
            }

            applyTheme(nextTheme);

            // Update button title
            const titles = {
                'light': 'Theme: Light',
                'dark': 'Theme: Dark',
                'system': 'Theme: System (Auto)'
            };
            this.setAttribute('title', titles[nextTheme]);

            // Save theme preference
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const updateThemeUrl = document.querySelector('meta[name="update-theme-url"]')?.content;

            if (isAuthenticated && isAuthenticated.content === 'true' && updateThemeUrl && csrfToken) {
                // Save to backend for authenticated users
                fetch(updateThemeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ theme_preference: nextTheme })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          currentTheme = nextTheme;
                          if (metaTheme) {
                              metaTheme.content = nextTheme;
                          }
                      }
                  })
                  .catch(error => console.error('Error updating theme:', error));
            } else {
                // Save to localStorage for guests
                localStorage.setItem('theme', nextTheme);
                currentTheme = nextTheme;
            }
        });
    }
}

// Sidebar Toggle functionality for regular user sidebar
function initSidebarToggle() {
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');

    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);
            const chevron = this.querySelector('.fa-chevron-down');

            if (submenu) {
                submenu.classList.toggle('hidden');
                if (chevron) {
                    chevron.classList.toggle('rotate-180');
                }
            }
        });
    });

    // Handle logout link in sidebar
    const sidebarLogoutLink = document.querySelector('[data-logout]');
    const sidebarLogoutForm = document.getElementById('sidebar-logout-form');

    if (sidebarLogoutLink && sidebarLogoutForm) {
        sidebarLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            sidebarLogoutForm.submit();
        });
    }
}

// Dropdown Menus for Header Navigation
function initDropdownMenus() {
    const dropdownContainers = document.querySelectorAll('.dropdown-container');

    dropdownContainers.forEach(function(container) {
        const toggle = container.querySelector('.dropdown-toggle');
        const menu = container.querySelector('.dropdown-menu');

        if (!toggle || !menu) return;

        let closeTimeout;

        // Ensure menu is hidden initially
        menu.style.display = 'none';

        // Toggle on click
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const isCurrentlyOpen = menu.style.display === 'block';

            // Close all other dropdowns
            dropdownContainers.forEach(function(otherContainer) {
                if (otherContainer !== container) {
                    const otherMenu = otherContainer.querySelector('.dropdown-menu');
                    if (otherMenu) {
                        otherMenu.style.display = 'none';
                    }
                }
            });

            // Toggle this dropdown
            if (isCurrentlyOpen) {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        });

        // Keep open on hover over the container
        container.addEventListener('mouseenter', function() {
            clearTimeout(closeTimeout);
        });

        // Close after delay when leaving the container
        container.addEventListener('mouseleave', function() {
            closeTimeout = setTimeout(function() {
                menu.style.display = 'none';
            }, 300);
        });

        // Prevent closing when hovering over the menu itself
        menu.addEventListener('mouseenter', function() {
            clearTimeout(closeTimeout);
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-container')) {
            dropdownContainers.forEach(function(container) {
                const menu = container.querySelector('.dropdown-menu');
                if (menu) {
                    menu.style.display = 'none';
                }
            });
        }
    });

    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            const desktopNav = document.querySelector('.md\\:flex.md\\:items-center');
            if (desktopNav) {
                desktopNav.classList.toggle('hidden');
                desktopNav.classList.toggle('flex');
            }
        });
    }

    // Mobile search toggle
    const mobileSearchToggle = document.getElementById('mobile-search-toggle');
    const mobileSearchForm = document.getElementById('mobile-search-form');
    if (mobileSearchToggle && mobileSearchForm) {
        mobileSearchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            mobileSearchForm.classList.toggle('hidden');
        });
    }
}

// Image Fallbacks
function initImageFallbacks() {
    // Handle images with data-fallback-src attribute
    document.querySelectorAll('img[data-fallback-src]').forEach(function(img) {
        img.addEventListener('error', function() {
            const fallbackSrc = this.getAttribute('data-fallback-src');
            if (fallbackSrc && this.src !== fallbackSrc) {
                this.src = fallbackSrc;
            }
        });
    });
}

// Profile Page Tab Switching
function initProfileTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabLinks.length === 0 || tabContents.length === 0) {
        return; // Not on a page with tabs
    }

    let chartsInitialized = false;

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);

            // Update active states on tab links
            tabLinks.forEach(l => {
                l.classList.remove('bg-blue-50', 'text-blue-700', 'font-medium');
                l.classList.add('text-gray-700');
                l.classList.add('dark:text-gray-300');
            });
            this.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');
            this.classList.remove('text-gray-700', 'dark:text-gray-300');

            // Hide all tab contents
            tabContents.forEach(content => {
                content.style.display = 'none';
            });

            // Show selected tab content
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.style.display = 'block';

                // Initialize charts when API tab is shown for the first time
                if (targetId === 'api' && !chartsInitialized) {
                    chartsInitialized = true;
                    // Small delay to ensure the tab is fully visible before rendering charts
                    setTimeout(() => {
                        // Wait for Chart.js to be available
                        let attempts = 0;
                        const maxAttempts = 20; // Try for up to 2 seconds
                        const checkChartJs = setInterval(() => {
                            attempts++;
                            if (typeof Chart !== 'undefined') {
                                clearInterval(checkChartJs);
                                initializeProfileCharts();
                            } else if (attempts >= maxAttempts) {
                                clearInterval(checkChartJs);
                                console.error('Chart.js failed to load within timeout period');
                            }
                        }, 100);
                    }, 50);
                }
            }

            // Update URL hash without scrolling
            history.pushState(null, null, '#' + targetId);
        });
    });

    // Handle initial hash
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        const link = document.querySelector(`a[href="#${hash}"]`);
        if (link) {
            link.click();
        }
    } else if (hash === '' || hash === 'general') {
        // If on general tab or no hash, ensure other tabs are hidden
        tabContents.forEach((content, index) => {
            if (index !== 0) {
                content.style.display = 'none';
            }
        });
    }
}

// Add to Cart function (standalone for backward compatibility)
window.addToCart = function(guid) {
    if (!guid) {
        console.error('No GUID provided to addToCart');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: guid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Added to cart successfully!', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Failed to add item to cart', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        if (typeof showToast === 'function') {
            showToast('An error occurred', 'error');
        }
    });
};

// Admin User Edit - Role Expiry DateTime Picker
function initAdminUserEdit() {
    // Only run if we're on the admin user edit page
    if (!document.getElementById('rolechangedate')) {
        return;
    }

    initializeDateTimePicker();
    setupTypeToSelect();
    setupExpiryDateHandlers();
}

// Initialize the datetime picker with existing values
function initializeDateTimePicker() {
    const hiddenInput = document.getElementById('rolechangedate');
    if (!hiddenInput) return;

    if (hiddenInput.value) {
        const date = new Date(hiddenInput.value);
        if (!isNaN(date.getTime())) {
            document.getElementById('expiry_year').value = date.getFullYear().toString();
            document.getElementById('expiry_month').value = String(date.getMonth() + 1).padStart(2, '0');
            document.getElementById('expiry_day').value = String(date.getDate()).padStart(2, '0');
            document.getElementById('expiry_hour').value = String(date.getHours()).padStart(2, '0');
            document.getElementById('expiry_minute').value = String(date.getMinutes()).padStart(2, '0');
            updateDateTimePreview();
        }
    }

    // Add change listeners to all selectors
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', updateDateTime);
        }
    });
}

// Setup type-to-select functionality for all dropdowns
function setupTypeToSelect() {
    const selects = ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'];

    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;

        let typedValue = '';
        let typingTimer;

        select.addEventListener('keypress', function(e) {
            clearTimeout(typingTimer);

            // Add typed character
            typedValue += e.key;

            // Find matching option
            const options = Array.from(this.options);
            const match = options.find(opt =>
                opt.value.startsWith(typedValue) ||
                opt.text.toLowerCase().startsWith(typedValue.toLowerCase())
            );

            if (match) {
                this.value = match.value;
                updateDateTime();

                // Visual feedback
                this.classList.add('ring-2', 'ring-green-500', 'dark:ring-green-400');
                setTimeout(() => {
                    this.classList.remove('ring-2', 'ring-green-500', 'dark:ring-green-400');
                }, 300);
            }

            // Clear typed value after 1 second
            typingTimer = setTimeout(() => {
                typedValue = '';
            }, 1000);
        });
    });
}

// Update the hidden input and preview when any selector changes
function updateDateTime() {
    const year = document.getElementById('expiry_year')?.value;
    const month = document.getElementById('expiry_month')?.value;
    const day = document.getElementById('expiry_day')?.value;
    const hour = document.getElementById('expiry_hour')?.value;
    const minute = document.getElementById('expiry_minute')?.value;

    if (year && month && day && hour && minute) {
        const dateTimeStr = `${year}-${month}-${day}T${hour}:${minute}:00`;
        document.getElementById('rolechangedate').value = dateTimeStr;
        updateDateTimePreview();

        // Show success flash on all filled selectors
        ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.value) {
                el.classList.add('border-green-500', 'dark:border-green-400');
                setTimeout(() => {
                    el.classList.remove('border-green-500', 'dark:border-green-400');
                }, 500);
            }
        });
    } else {
        const preview = document.getElementById('datetime_preview');
        if (preview) {
            preview.classList.add('hidden');
        }
    }
}

// Update the datetime preview display
function updateDateTimePreview() {
    const hiddenInput = document.getElementById('rolechangedate');
    const preview = document.getElementById('datetime_preview');
    const display = document.getElementById('datetime_display');

    if (!hiddenInput || !preview || !display) return;

    if (hiddenInput.value) {
        const date = new Date(hiddenInput.value);
        const options = {
            weekday: 'short',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        display.textContent = date.toLocaleDateString('en-US', options);
        preview.classList.remove('hidden');

        // Check if date is in past or expiring soon
        const now = new Date();
        const diff = date - now;
        if (diff < 0) {
            display.classList.add('text-red-600', 'dark:text-red-400');
            display.classList.remove('text-blue-600', 'dark:text-blue-400', 'text-yellow-600', 'dark:text-yellow-400');
        } else if (diff < 7 * 24 * 60 * 60 * 1000) {
            display.classList.add('text-yellow-600', 'dark:text-yellow-400');
            display.classList.remove('text-blue-600', 'dark:text-blue-400', 'text-red-600', 'dark:text-red-400');
        } else {
            display.classList.add('text-blue-600', 'dark:text-blue-400');
            display.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
        }
    } else {
        preview.classList.add('hidden');
    }
}

// Setup event handlers for expiry date quick actions
function setupExpiryDateHandlers() {
    // Event delegation for all expiry date buttons
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-expiry-action]');
        if (!target) return;

        e.preventDefault();
        const action = target.getAttribute('data-expiry-action');
        const days = parseInt(target.getAttribute('data-days') || '0', 10);
        const hours = parseInt(target.getAttribute('data-hours') || '0', 10);

        if (action === 'set') {
            setExpiryDateTime(days, hours);
        } else if (action === 'end-of-day') {
            setEndOfDay();
        } else if (action === 'clear') {
            clearExpiryDate();
        }
    });
}

// Function to set expiry date and time by adding days and hours (stackable)
function setExpiryDateTime(days, hours) {
    let baseDate;

    // Check if there's already a selected datetime
    const year = document.getElementById('expiry_year')?.value;
    const month = document.getElementById('expiry_month')?.value;
    const day = document.getElementById('expiry_day')?.value;
    const hour = document.getElementById('expiry_hour')?.value;
    const minute = document.getElementById('expiry_minute')?.value;

    if (year && month && day && hour && minute) {
        // Use existing selected datetime as base
        baseDate = new Date(year, parseInt(month) - 1, day, hour, minute);
        showExpiryToast('Added ' + (days > 0 ? days + ' day' + (days !== 1 ? 's' : '') : '') + (days > 0 && hours > 0 ? ' and ' : '') + (hours > 0 ? hours + ' hour' + (hours !== 1 ? 's' : '') : ''), 'info');
    } else {
        // Start from current time
        baseDate = new Date();
        showExpiryToast('Expiry date set to ' + formatDateTimeForDisplay(baseDate), 'success');
    }

    // Add the days and hours
    baseDate.setDate(baseDate.getDate() + days);
    baseDate.setHours(baseDate.getHours() + hours);

    // Update all selectors
    document.getElementById('expiry_year').value = baseDate.getFullYear().toString();
    document.getElementById('expiry_month').value = String(baseDate.getMonth() + 1).padStart(2, '0');
    document.getElementById('expiry_day').value = String(baseDate.getDate()).padStart(2, '0');
    document.getElementById('expiry_hour').value = String(baseDate.getHours()).padStart(2, '0');
    document.getElementById('expiry_minute').value = String(baseDate.getMinutes()).padStart(2, '0');

    updateDateTime();

    // Add animated visual feedback
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-green-500', 'dark:ring-green-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-green-500', 'dark:ring-green-400', 'scale-105');
            }, 600);
        }
    });
}

// Function to set expiry to end of current day (23:59)
function setEndOfDay() {
    const endOfDay = new Date();
    endOfDay.setHours(23, 59, 0, 0);

    document.getElementById('expiry_year').value = endOfDay.getFullYear().toString();
    document.getElementById('expiry_month').value = String(endOfDay.getMonth() + 1).padStart(2, '0');
    document.getElementById('expiry_day').value = String(endOfDay.getDate()).padStart(2, '0');
    document.getElementById('expiry_hour').value = '23';
    document.getElementById('expiry_minute').value = '55';

    updateDateTime();

    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-indigo-500', 'dark:ring-indigo-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-indigo-500', 'dark:ring-indigo-400', 'scale-105');
            }, 600);
        }
    });

    showExpiryToast('Expiry set to end of today (23:59)', 'info');
}

// Function to clear expiry date
function clearExpiryDate() {
    document.getElementById('expiry_year').value = '';
    document.getElementById('expiry_month').value = '';
    document.getElementById('expiry_day').value = '';
    document.getElementById('expiry_hour').value = '';
    document.getElementById('expiry_minute').value = '';
    document.getElementById('rolechangedate').value = '';
    const preview = document.getElementById('datetime_preview');
    if (preview) {
        preview.classList.add('hidden');
    }

    // Add a visual feedback with gray pulse
    ['expiry_year', 'expiry_month', 'expiry_day', 'expiry_hour', 'expiry_minute'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('ring-2', 'ring-gray-500', 'dark:ring-gray-400', 'scale-105');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-gray-500', 'dark:ring-gray-400', 'scale-105');
            }, 600);
        }
    });

    showExpiryToast('Expiry date cleared - role is now permanent', 'info');
}

// Format date for display
function formatDateTimeForDisplay(date) {
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
}

// Show toast notification specific to expiry date changes
function showExpiryToast(message, type) {
    type = type || 'success';

    // Remove existing toast if any
    const existingToast = document.getElementById('expiryToast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast
    const toast = document.createElement('div');
    toast.id = 'expiryToast';
    toast.className = 'fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50 transform transition-all duration-300 translate-y-0 opacity-100';

    if (type === 'success') {
        toast.classList.add('bg-green-500', 'dark:bg-green-600', 'text-white');
        toast.innerHTML = '<i class="fa fa-check-circle"></i><span>' + escapeHtml(message) + '</span>';
    } else if (type === 'info') {
        toast.classList.add('bg-blue-500', 'dark:bg-blue-600', 'text-white');
        toast.innerHTML = '<i class="fa fa-info-circle"></i><span>' + escapeHtml(message) + '</span>';
    }

    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    }, 10);

    // Remove after 3 seconds with fade out
    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// My Movies page functionality
function initMyMovies() {
    // Confirm before removing movies
    document.querySelectorAll('.confirm_action').forEach(element => {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this movie from your watchlist?')) {
                e.preventDefault();
            }
        });
    });

    // Image fallback for movie covers
    document.querySelectorAll('img[data-fallback-src]').forEach(img => {
        img.addEventListener('error', function() {
            const fallback = this.getAttribute('data-fallback-src');
            if (fallback && this.src !== fallback) {
                this.src = fallback;
            }
        });
    });

    // Initialize Bootstrap tooltips if they exist
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
}

// Auth pages functionality
function initAuthPages() {
    // Auto-hide success messages after 5 seconds on login page
    if (window.location.pathname.includes('/login')) {
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-50, .bg-blue-50');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    }

    // 2FA verification - Auto-submit when 6 digits are entered
    const otpInput = document.getElementById('one_time_password');
    if (otpInput) {
        otpInput.addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');

            // Auto-submit when 6 digits are entered
            if (this.value.length === 6) {
                // Small delay to show the complete code before submitting
                setTimeout(() => {
                    this.form.submit();
                }, 300);
            }
        });

        // Focus on input when page loads
        otpInput.focus();
    }
}

// Profile edit page functionality
function initProfileEdit() {
    // 2FA Disable Form Toggle
    const toggleBtn = document.getElementById('toggle-disable-2fa-btn');
    const cancelBtn = document.getElementById('cancel-disable-2fa-btn');
    const formContainer = document.getElementById('disable-2fa-form-container');

    if (toggleBtn && formContainer) {
        toggleBtn.addEventListener('click', function() {
            formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (cancelBtn && formContainer) {
        cancelBtn.addEventListener('click', function() {
            formContainer.style.display = 'none';
            // Clear password field
            const passwordInput = document.getElementById('disable_2fa_password');
            if (passwordInput) {
                passwordInput.value = '';
            }
        });
    }

    // Theme preference instant preview
    const themeRadios = document.querySelectorAll('input[name="theme_preference"]');
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function applyTheme(themeValue) {
        const html = document.documentElement;

        if (themeValue === 'system') {
            // Use system preference
            if (mediaQuery.matches) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        } else if (themeValue === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
    }

    themeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            applyTheme(this.value);
        });
    });

    // Listen to system preference changes if 'system' is selected
    mediaQuery.addEventListener('change', function(e) {
        const selectedTheme = document.querySelector('input[name="theme_preference"]:checked')?.value;
        if (selectedTheme === 'system') {
            applyTheme('system');
        }
    });
}

function initDetailsPageImageModal() {
    // Handle image modal triggers on release details page
    const imageModalTriggers = document.querySelectorAll('.image-modal-trigger');
    const imageModal = document.getElementById('imageModal');
    const imageModalImage = document.getElementById('imageModalImage');
    const imageModalTitle = document.getElementById('imageModalTitle');
    const closeButtons = document.querySelectorAll('[data-close-image-modal]');

    if (!imageModal || !imageModalImage || !imageModalTitle) {
        return; // Not on the details page
    }

    // Add click handlers to image triggers
    imageModalTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const imageUrl = this.getAttribute('data-image-url');
            const imageTitle = this.getAttribute('data-image-title') || 'Image Preview';

            // Use the image URL as-is (keeping _thumb suffix)
            imageModalImage.src = imageUrl;
            imageModalTitle.textContent = imageTitle;
            imageModal.classList.remove('hidden');
            imageModal.style.display = 'flex';
        });
    });

    // Add click handler to close button
    closeButtons.forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        });
    });

    // Close modal when clicking on backdrop
    imageModal.addEventListener('click', function(e) {
        if (e.target === imageModal) {
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !imageModal.classList.contains('hidden')) {
            imageModal.classList.add('hidden');
            imageModal.style.display = 'none';
        }
    });
}

// Movies page layout toggle functionality
function initMoviesLayoutToggle() {
    const toggleButton = document.getElementById('layoutToggle');
    const toggleText = document.getElementById('layoutToggleText');
    const moviesGrid = document.getElementById('moviesGrid');

    if (!toggleButton || !toggleText || !moviesGrid) {
        return; // Not on movies page
    }

    // Get current layout from data attribute
    let currentLayout = parseInt(moviesGrid.dataset.userLayout) || 2;

    // Apply current layout
    applyLayout(currentLayout);

    // Handle toggle button click
    toggleButton.addEventListener('click', function() {
        // Toggle between 1 and 2 column layouts
        currentLayout = currentLayout === 2 ? 1 : 2;

        // Save preference to server
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken || !csrfToken.content) {
            console.error('CSRF token not found');
            applyLayout(currentLayout); // Apply layout anyway
            return;
        }

        fetch('/movies/update-layout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content
            },
            body: JSON.stringify({ layout: currentLayout })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    applyLayout(currentLayout);
                }
            })
            .catch(error => {
                console.error('Error saving layout preference:', error);
                // Apply layout anyway even if save fails
                applyLayout(currentLayout);
            });
    });

    function applyLayout(layout) {
        const images = moviesGrid.querySelectorAll('img[alt], .bg-gray-200.dark\\:bg-gray-700');
        const releaseContainers = moviesGrid.querySelectorAll('.release-card-container');

        if (layout === 1) {
            // 1 Column Layout
            moviesGrid.classList.remove('lg:grid-cols-2');
            moviesGrid.classList.add('grid-cols-1');
            if (toggleText) toggleText.textContent = '1 Column';
            const icon = toggleButton.querySelector('i');
            if (icon) icon.className = 'fas fa-th-list mr-2';

            // Make images larger
            images.forEach(img => {
                img.classList.remove('w-32', 'h-48');
                img.classList.add('w-48', 'h-72');
            });

            // Adjust release card containers to horizontal layout
            releaseContainers.forEach(container => {
                container.classList.remove('space-y-2');
                container.classList.add('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');

                const infoWrapper = container.querySelector('.release-info-wrapper');
                if (infoWrapper) {
                    infoWrapper.classList.add('flex-1', 'min-w-0');
                }

                const actions = container.querySelector('.release-actions');
                if (actions) {
                    actions.classList.remove('flex-wrap');
                    actions.classList.add('flex-shrink-0', 'flex-row', 'items-center');
                }
            });
        } else {
            // 2 Column Layout
            moviesGrid.classList.remove('grid-cols-1');
            moviesGrid.classList.add('lg:grid-cols-2');
            if (toggleText) toggleText.textContent = '2 Columns';
            const icon = toggleButton.querySelector('i');
            if (icon) icon.className = 'fas fa-th-large mr-2';

            // Make images smaller
            images.forEach(img => {
                img.classList.remove('w-48', 'h-72');
                img.classList.add('w-32', 'h-48');
            });

            // Adjust release card containers to vertical layout
            releaseContainers.forEach(container => {
                container.classList.add('space-y-2');
                container.classList.remove('flex', 'flex-row', 'items-start', 'justify-between', 'gap-3');

                const infoWrapper = container.querySelector('.release-info-wrapper');
                if (infoWrapper) {
                    infoWrapper.classList.remove('flex-1', 'min-w-0');
                }

                const actions = container.querySelector('.release-actions');
                if (actions) {
                    actions.classList.add('flex-wrap', 'items-center');
                    actions.classList.remove('flex-shrink-0', 'flex-row');
                }
            });
        }
    }
}

function initMobileEnhancements() {
    // Handle mobile-specific enhancements
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('flex');
            }
        });
    }
}

function initAdminDashboardCharts() {
    // Only initialize if Chart.js is available and we're on a page with charts
    if (typeof Chart === 'undefined') {
        // If Chart.js is not loaded yet, wait for it
        let attempts = 0;
        const maxAttempts = 30; // Try for up to 3 seconds
        const checkChartJs = setInterval(() => {
            attempts++;
            if (typeof Chart !== 'undefined') {
                clearInterval(checkChartJs);
                initializeAllDashboardCharts();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkChartJs);
                console.warn('Chart.js not loaded - charts will not be displayed');
            }
        }, 100);
        return;
    }

    initializeAllDashboardCharts();
}

function initializeAllDashboardCharts() {
    // Check if any chart canvas elements exist
    const hasUserStatsCharts = document.getElementById('downloadsChart') ||
                                 document.getElementById('apiHitsChart') ||
                                 document.getElementById('downloadsMinuteChart') ||
                                 document.getElementById('apiHitsMinuteChart');

    const hasSystemCharts = document.getElementById('cpuHistory24hChart') ||
                            document.getElementById('ramHistory24hChart') ||
                            document.getElementById('cpuHistory30dChart') ||
                            document.getElementById('ramHistory30dChart');

    if (!hasUserStatsCharts && !hasSystemCharts) {
        return; // Not on admin dashboard or charts not present
    }

    // Initialize user statistics charts
    initializeUserStatCharts();

    // Initialize system metrics charts
    initializeSystemMetricsCharts();
}

function initializeUserStatCharts() {
    // Downloads Chart (Last 7 Days - Hourly)
    const downloadsCanvas = document.getElementById('downloadsChart');
    if (downloadsCanvas) {
        const data = JSON.parse(downloadsCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(downloadsCanvas, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'Downloads',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // API Hits Chart (Last 7 Days - Hourly)
    const apiHitsCanvas = document.getElementById('apiHitsChart');
    if (apiHitsCanvas) {
        const data = JSON.parse(apiHitsCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(apiHitsCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'API Hits',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(168, 85, 247, 0.2)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // Downloads Per Minute Chart (Last 60 Minutes)
    const downloadsMinuteCanvas = document.getElementById('downloadsMinuteChart');
    if (downloadsMinuteCanvas) {
        const data = JSON.parse(downloadsMinuteCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(downloadsMinuteCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'Downloads',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(34, 197, 94, 0.2)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // API Hits Per Minute Chart (Last 60 Minutes)
    const apiHitsMinuteCanvas = document.getElementById('apiHitsMinuteChart');
    if (apiHitsMinuteCanvas) {
        const data = JSON.parse(apiHitsMinuteCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(apiHitsMinuteCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'API Hits',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(168, 85, 247, 0.2)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
}

function initializeSystemMetricsCharts() {
    // CPU Usage 24h Chart
    const cpuHistory24hCanvas = document.getElementById('cpuHistory24hChart');
    if (cpuHistory24hCanvas) {
        const history = JSON.parse(cpuHistory24hCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(cpuHistory24hCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'CPU Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // RAM Usage 24h Chart
    const ramHistory24hCanvas = document.getElementById('ramHistory24hChart');
    if (ramHistory24hCanvas) {
        const history = JSON.parse(ramHistory24hCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(ramHistory24hCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'RAM Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(6, 182, 212, 0.2)',
                        borderColor: 'rgba(6, 182, 212, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // CPU Usage 30d Chart
    const cpuHistory30dCanvas = document.getElementById('cpuHistory30dChart');
    if (cpuHistory30dCanvas) {
        const history = JSON.parse(cpuHistory30dCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(cpuHistory30dCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'CPU Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // RAM Usage 30d Chart
    const ramHistory30dCanvas = document.getElementById('ramHistory30dChart');
    if (ramHistory30dCanvas) {
        const history = JSON.parse(ramHistory30dCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(ramHistory30dCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'RAM Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(6, 182, 212, 0.2)',
                        borderColor: 'rgba(6, 182, 212, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
}

function initAdminGroups() {
    // Placeholder for admin groups functionality
    // Group management is handled in event delegation
}

// TinyMCE Initialization for Admin Content Pages
function initTinyMCE() {
    // Only initialize if TinyMCE editor element exists (check for #body or .tinymce-editor)
    if (!document.getElementById('body') && !document.querySelector('.tinymce-editor')) {
        return;
    }

    // Function to dynamically load TinyMCE from CDN
    function loadTinyMCEScript() {
        return new Promise(function(resolve, reject) {
            // Check if already loaded
            if (typeof tinymce !== 'undefined') {
                resolve();
                return;
            }

            // Get API key from textarea data attribute or create meta tag
            const bodyTextarea = document.getElementById('body');
            const tinymceEditors = document.querySelectorAll('.tinymce-editor');
            let apiKey = 'no-api-key';

            // Try to get from existing meta tag first
            let apiKeyMeta = document.querySelector('meta[name="tinymce-api-key"]');

            if (!apiKeyMeta) {
                // Create meta tag dynamically if it doesn't exist
                // Get API key from window config or use default
                if (window.NNTmuxConfig && window.NNTmuxConfig.tinymceApiKey) {
                    apiKey = window.NNTmuxConfig.tinymceApiKey;
                } else if (bodyTextarea && bodyTextarea.dataset.tinymceApiKey) {
                    apiKey = bodyTextarea.dataset.tinymceApiKey;
                } else if (tinymceEditors.length > 0) {
                    // Check if any tinymce-editor has the API key
                    for (let i = 0; i < tinymceEditors.length; i++) {
                        if (tinymceEditors[i].dataset.tinymceApiKey) {
                            apiKey = tinymceEditors[i].dataset.tinymceApiKey;
                            break;
                        }
                    }
                }

                // Create and append meta tag
                apiKeyMeta = document.createElement('meta');
                apiKeyMeta.setAttribute('name', 'tinymce-api-key');
                apiKeyMeta.setAttribute('content', apiKey);
                document.head.appendChild(apiKeyMeta);
            } else {
                apiKey = apiKeyMeta.content;
            }

            // Create script element
            const script = document.createElement('script');
            script.src = 'https://cdn.tiny.cloud/1/' + apiKey + '/tinymce/8/tinymce.min.js';
            script.referrerPolicy = 'origin';

            script.onload = function() {
                console.log('TinyMCE script loaded from CDN');
                resolve();
            };

            script.onerror = function() {
                console.error('Failed to load TinyMCE script from CDN');
                reject(new Error('Failed to load TinyMCE'));
            };

            // Append to head
            document.head.appendChild(script);
        });
    }

    // Function to detect if dark mode is active
    function isDarkMode() {
        return document.documentElement.classList.contains('dark') ||
            (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    // Function to get TinyMCE config
    function getTinyMCEConfig() {
        const darkMode = isDarkMode();
        const apiKeyMeta = document.querySelector('meta[name="tinymce-api-key"]');
        const apiKey = apiKeyMeta ? apiKeyMeta.content : 'no-api-key';

        return {
            selector: '#body, .tinymce-editor',
            height: 500,
            menubar: true,
            skin: darkMode ? 'oxide-dark' : 'oxide',
            content_css: darkMode ? 'dark' : 'default',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | ' +
                'bold italic underline strikethrough | forecolor backcolor | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | ' +
                'link image media table emoticons | ' +
                'removeformat code fullscreen | help',
            toolbar_mode: 'sliding',
            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            branding: false,
            promotion: false,
            resize: true,
            statusbar: true,
            elementpath: true,
            automatic_uploads: false,
            file_picker_types: 'image',
            font_family_formats: 'Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,palatino,serif; Tahoma=tahoma,arial,helvetica,sans-serif; Times New Roman=times new roman,times,serif; Verdana=verdana,geneva,sans-serif',
            font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
            autolink_pattern: /^(https?:\/\/|www\.|(?!www\.)[a-z0-9\-]+\.[a-z]{2,13})/i,
            link_default_protocol: 'https',
            link_assume_external_targets: true,
            link_target_list: [
                {title: 'None', value: ''},
                {title: 'New window', value: '_blank'},
                {title: 'Same window', value: '_self'}
            ],
            block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre; Blockquote=blockquote',
            valid_elements: '*[*]',
            extended_valid_elements: '*[*]',
            valid_children: '+body[style]',
            paste_as_text: false,
            paste_block_drop: false,
            paste_data_images: true,
            image_advtab: true,
            image_caption: true,
            image_description: true,
            image_dimensions: true,
            image_title: true,
            table_default_attributes: {
                border: '1'
            },
            table_default_styles: {
                'border-collapse': 'collapse',
                'width': '100%'
            },
            emoticons_database: 'emojis',
            setup: function(editor) {
                // Auto-save on content change
                editor.on('change', function() {
                    editor.save();
                });
                // Auto-save on blur (when editor loses focus)
                editor.on('blur', function() {
                    editor.save();
                });
                // Auto-save on keyup (for continuous saving)
                editor.on('keyup', function() {
                    editor.save();
                });
                // Save before form submission
                editor.on('submit', function() {
                    editor.save();
                });
            }
        };
    }

    // Function to actually initialize TinyMCE once it's loaded
    function doInitTinyMCE() {
        // Initialize TinyMCE
        tinymce.init(getTinyMCEConfig()).then(function(editors) {
            if (editors && editors.length > 0) {
                console.log('TinyMCE initialized successfully for ' + editors.length + ' editor(s)');

                // Add form submission handler to sync content for all editors
                editors.forEach(function(editor) {
                    const textarea = document.getElementById(editor.id);
                    if (textarea && textarea.form) {
                        // Remove any existing listeners to avoid duplicates
                        const form = textarea.form;
                        if (!form.hasAttribute('data-tinymce-handler')) {
                            form.addEventListener('submit', function(e) {
                                // Sync all TinyMCE editors before form submission
                                tinymce.triggerSave();
                                console.log('TinyMCE content synced to textareas before form submission');
                            });
                            form.setAttribute('data-tinymce-handler', 'true');
                        }
                    }
                });
            }
        }).catch(function(error) {
            console.error('TinyMCE initialization failed:', error);
        });

        // Watch for theme changes and reinitialize TinyMCE
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    // Get all active TinyMCE editors
                    const allEditors = tinymce.editors;
                    if (allEditors && allEditors.length > 0) {
                        // Save content from all editors
                        const editorContents = {};
                        allEditors.forEach(function(editor) {
                            editorContents[editor.id] = editor.getContent();
                        });

                        // Remove all editors
                        tinymce.remove();

                        // Reinitialize with new theme
                        tinymce.init(getTinyMCEConfig()).then(function(editors) {
                            // Restore content to each editor
                            if (editors && editors.length > 0) {
                                editors.forEach(function(editor) {
                                    if (editorContents[editor.id]) {
                                        editor.setContent(editorContents[editor.id]);
                                    }
                                });
                            }
                        });
                    }
                }
            });
        });

        // Start observing theme changes on the html element
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    // Load TinyMCE script dynamically, then initialize
    loadTinyMCEScript()
        .then(function() {
            console.log('TinyMCE available, initializing editor...');
            doInitTinyMCE();
        })
        .catch(function(error) {
            console.error('Failed to load TinyMCE:', error);
            // Show error message to user for all TinyMCE textareas
            const textareas = document.querySelectorAll('#body, .tinymce-editor');
            textareas.forEach(function(textarea) {
                if (textarea && textarea.parentElement) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mt-2 p-3 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 rounded';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>TinyMCE editor failed to load. Please refresh the page or check your internet connection.';
                    textarea.parentElement.insertBefore(errorDiv, textarea.nextSibling);
                }
            });
        });
}

// Admin Image Preview functionality
function initAdminImagePreview() {
    // Handle admin page image previews (for cover images, etc.)
    // This uses the existing image modal functionality
    document.addEventListener('click', function(e) {
        const imageTrigger = e.target.closest('[data-admin-image-preview]');
        if (imageTrigger) {
            e.preventDefault();
            const imageUrl = imageTrigger.getAttribute('data-admin-image-preview');
            if (imageUrl && typeof openImageModal === 'function') {
                openImageModal(imageUrl);
            }
        }
    });

    // Preview image on file input change (for admin edit pages)
    document.querySelectorAll('input[type="file"][accept*="image"]').forEach(function(fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Find preview container
                    const previewContainer = fileInput.closest('div').querySelector('.image-preview');
                    if (previewContainer) {
                        let previewImg = previewContainer.querySelector('img');
                        if (!previewImg) {
                            previewImg = document.createElement('img');
                            previewImg.className = 'w-full h-auto rounded-lg';
                            previewContainer.innerHTML = '';
                            previewContainer.appendChild(previewImg);
                        }
                        previewImg.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Admin Invitations Select All functionality
function initAdminInvitationsSelectAll() {
    const selectAllCheckbox = document.getElementById('select_all');
    if (!selectAllCheckbox) {
        return; // Not on invitations page
    }

    const invitationCheckboxes = document.querySelectorAll('.invitation-checkbox');

    // Handle select all checkbox change
    selectAllCheckbox.addEventListener('change', function() {
        invitationCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Handle individual checkbox changes to update select all state
    invitationCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(invitationCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(invitationCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        });
    });

    // Initialize the select all state on page load
    const allChecked = invitationCheckboxes.length > 0 && Array.from(invitationCheckboxes).every(cb => cb.checked);
    selectAllCheckbox.checked = allChecked;
}

// Admin Regex Form Validation
function initAdminRegexFormValidation() {
    const regexForm = document.getElementById('regexForm');
    if (!regexForm) {
        return; // Not on a regex edit page
    }

    // Validate regex pattern on form submit
    regexForm.addEventListener('submit', function(e) {
        const regexInputs = regexForm.querySelectorAll('input[name*="regex"], textarea[name*="regex"]');
        let isValid = true;
        const errors = [];

        regexInputs.forEach(input => {
            const value = input.value.trim();
            if (value && input.hasAttribute('required')) {
                // Basic regex validation - check if it looks like a valid regex pattern
                // Regex should typically start with a delimiter (/, #, ~, etc.)
                // and have matching delimiters
                if (value.length > 0) {
                    const firstChar = value[0];
                    const delimiters = ['/', '#', '~', '%', '@', '!',];

                    if (delimiters.includes(firstChar)) {
                        // Check if regex has matching closing delimiter
                        let delimiterCount = 0;
                        let flags = '';
                        for (let i = 0; i < value.length; i++) {
                            if (value[i] === firstChar && i > 0) {
                                delimiterCount++;
                                // Check if there are flags after this delimiter
                                flags = value.substring(i + 1);
                                break;
                            }
                        }

                        if (delimiterCount === 0) {
                            isValid = false;
                            errors.push(`Regex pattern "${input.name}" is missing closing delimiter "${firstChar}"`);
                            input.classList.add('border-red-500');
                        } else {
                            input.classList.remove('border-red-500');
                            // Validate flags if present
                            if (flags && !/^[gimsux]*$/.test(flags)) {
                                isValid = false;
                                errors.push(`Invalid regex flags in "${input.name}". Allowed: g, i, m, s, u, x`);
                                input.classList.add('border-red-500');
                            }
                        }
                    } else if (value.length > 0) {
                        // Regex doesn't start with a delimiter, might still be valid but warn
                        input.classList.add('border-yellow-500');
                    }
                }
            }
        });

        if (!isValid && errors.length > 0) {
            e.preventDefault();
            const errorMsg = errors.join('\n');
            if (typeof showToast === 'function') {
                showToast('Please fix regex validation errors', 'error');
            } else {
                alert(errorMsg);
            }
        }
    });

    // Real-time validation feedback for regex inputs
    const regexInputs = regexForm.querySelectorAll('input[name*="regex"], textarea[name*="regex"]');
    regexInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                const firstChar = value[0];
                const delimiters = ['/', '#', '~', '%', '@', '!',];

                if (delimiters.includes(firstChar)) {
                    let delimiterCount = 0;
                    for (let i = 1; i < value.length; i++) {
                        if (value[i] === firstChar) {
                            delimiterCount++;
                            break;
                        }
                    }

                    if (delimiterCount === 0) {
                        this.classList.add('border-red-500');
                        this.classList.remove('border-green-500');
                    } else {
                        this.classList.remove('border-red-500');
                        this.classList.add('border-green-500');
                    }
                } else {
                    this.classList.remove('border-red-500', 'border-green-500');
                }
            } else {
                this.classList.remove('border-red-500', 'border-green-500');
            }
        });
    });
}

// Admin Tmux Select Colors functionality
function initAdminTmuxSelectColors() {
    // This function is for future tmux color selection functionality
    // Currently, no color selection is needed in tmux settings
    // If color pickers are added to tmux-edit page in the future, add them here

    // Check if we're on a page that might have color inputs
    const colorInputs = document.querySelectorAll('input[type="color"], select[data-color-select]');
    if (colorInputs.length > 0) {
        colorInputs.forEach(function(input) {
            // Add color preview functionality if needed
            if (input.type === 'color') {
                input.addEventListener('change', function() {
                    // Update preview or related elements if needed
                    const preview = input.closest('.form-group')?.querySelector('.color-preview');
                    if (preview) {
                        preview.style.backgroundColor = input.value;
                    }
                });
            }
        });
    }
}

// Initialize all admin-specific features
function initAdminSpecificFeatures() {
    initAdminImagePreview();
    initAdminInvitationsSelectAll();
    initAdminRegexFormValidation();
    initAdminTmuxSelectColors();
    initAdminDeletedUsers();

    // Close delete modal when clicking outside
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }

    // Close verify modal when clicking outside
    const verifyUserModal = document.getElementById('verifyUserModal');
    if (verifyUserModal) {
        verifyUserModal.addEventListener('click', function(event) {
            if (event.target === this) {
                hideVerifyModal();
            }
        });
    }
}

// Admin Deleted Users page functionality
function initAdminDeletedUsers() {
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Update selectAll state when individual checkboxes change
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(userCheckboxes).some(cb => cb.checked);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
        });
    });

    // Bulk action form submit handler
    const bulkActionForm = document.getElementById('bulkActionForm');
    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const action = document.getElementById('bulkAction')?.value;
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const validationError = document.getElementById('validationError');
            const validationErrorMessage = document.getElementById('validationErrorMessage');

            // Hide any previous validation errors
            if (validationError) {
                validationError.classList.add('hidden');
            }

            // Validate action selected
            if (!action) {
                if (validationError && validationErrorMessage) {
                    validationErrorMessage.textContent = 'Please select an action from the dropdown.';
                    validationError.classList.remove('hidden');
                }
                return false;
            }

            // Validate at least one user selected
            if (checkedBoxes.length === 0) {
                if (validationError && validationErrorMessage) {
                    validationErrorMessage.textContent = 'Please select at least one user.';
                    validationError.classList.remove('hidden');
                }
                return false;
            }

            const count = checkedBoxes.length;
            const actionText = action === 'restore' ? 'restore' : 'permanently delete';
            const type = action === 'restore' ? 'success' : 'danger';
            const title = action === 'restore' ? 'Restore Users' : 'Delete Users';

            showConfirm({
                title: title,
                message: `Are you sure you want to ${actionText} ${count} user${count > 1 ? 's' : ''}?`,
                type: type,
                confirmText: action === 'restore' ? 'Restore' : 'Delete',
                onConfirm: function() {
                    bulkActionForm.submit();
                }
            });
        });
    }

    // Event delegation for restore buttons
    document.addEventListener('click', function(e) {
        const restoreBtn = e.target.closest('.restore-user-btn');
        if (restoreBtn) {
            e.preventDefault();
            const userId = restoreBtn.dataset.userId;
            const username = restoreBtn.dataset.username;
            if (userId && username) {
                restoreUser(userId, username);
            }
        }

        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            e.preventDefault();
            const userId = deleteBtn.dataset.userId;
            const username = deleteBtn.dataset.username;
            if (userId && username) {
                permanentDeleteUser(userId, username);
            }
        }
    });

    // Bulk action confirmation (kept for backward compatibility)
    window.confirmBulkAction = function(event) {
        event?.preventDefault();

        const action = document.getElementById('bulkAction')?.value;
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const validationError = document.getElementById('validationError');
        const validationErrorMessage = document.getElementById('validationErrorMessage');

        if (!action) {
            if (validationError && validationErrorMessage) {
                validationErrorMessage.textContent = 'Please select an action from the dropdown.';
                validationError.classList.remove('hidden');
            }
            return false;
        }

        if (checkedBoxes.length === 0) {
            if (validationError && validationErrorMessage) {
                validationErrorMessage.textContent = 'Please select at least one user.';
                validationError.classList.remove('hidden');
            }
            return false;
        }

        // Hide validation error if showing
        if (validationError) {
            validationError.classList.add('hidden');
        }

        const count = checkedBoxes.length;
        const actionText = action === 'restore' ? 'restore' : 'permanently delete';
        const type = action === 'restore' ? 'success' : 'danger';
        const title = action === 'restore' ? 'Restore Users' : 'Delete Users';

        showConfirm({
            title: title,
            message: `Are you sure you want to ${actionText} ${count} user${count > 1 ? 's' : ''}?`,
            type: type,
            confirmText: action === 'restore' ? 'Restore' : 'Delete',
            onConfirm: function() {
                document.getElementById('bulkActionForm')?.submit();
            }
        });

        return false;
    };
}

// Individual user restore/delete actions for deleted users page
window.restoreUser = function(userId, username) {
    showConfirm({
        title: 'Restore User',
        message: `Are you sure you want to restore user '${username}'?`,
        type: 'success',
        confirmText: 'Restore',
        onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) {
                const baseUrl = window.location.origin;
                form.action = `${baseUrl}/admin/deleted-users/restore/${userId}`;
                form.submit();
            }
        }
    });
};

window.permanentDeleteUser = function(userId, username) {
    showConfirm({
        title: 'Permanently Delete User',
        message: `Are you sure you want to PERMANENTLY delete user '${username}'?`,
        details: 'This action cannot be undone!',
        type: 'danger',
        confirmText: 'Delete Permanently',
        onConfirm: function() {
            const form = document.getElementById('individualActionForm');
            if (form) {
                const baseUrl = window.location.origin;
                // Use the correct route: permanent-delete
                form.action = `${baseUrl}/admin/deleted-users/permanent-delete/${userId}`;
                form.submit();
            }
        }
    });
};

// Recent Activity Auto-Refresh (20 minutes)
function initRecentActivityRefresh() {
    const activityContainer = document.getElementById('recent-activity-container');
    if (!activityContainer) {
        return; // Not on admin dashboard page
    }

    const refreshUrl = activityContainer.getAttribute('data-refresh-url');
    if (!refreshUrl) {
        return;
    }

    // Function to refresh activity data
    function refreshRecentActivity() {
        fetch(refreshUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.activities) {
                updateActivityDisplay(data.activities);
                updateLastRefreshTime();
            }
        })
        .catch(error => {
            console.error('Error refreshing recent activity:', error);
        });
    }

    // Function to update the activity display
    function updateActivityDisplay(activities) {
        const container = document.getElementById('recent-activity-container');
        if (!container) return;

        // Clear existing content
        container.innerHTML = '';

        if (activities.length === 0) {
            container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4" id="no-activity-message">No recent activity</p>';
            return;
        }

        // Add each activity item
        activities.forEach(activity => {
            const activityItem = document.createElement('div');
            activityItem.className = 'flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors';

            activityItem.innerHTML = `
                <div class="w-8 h-8 ${activity.icon_bg} rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                    <i class="fas fa-${activity.icon} ${activity.icon_color} text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-800 dark:text-gray-200">${escapeHtml(activity.message)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(activity.created_at)}</p>
                </div>
            `;

            container.appendChild(activityItem);
        });
    }

    // Function to update last refresh time
    function updateLastRefreshTime() {
        const lastUpdatedElement = document.getElementById('activity-last-updated');
        if (lastUpdatedElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            lastUpdatedElement.innerHTML = `<i class="fas fa-sync-alt"></i> Last updated: ${timeString}`;
        }
    }

    // Helper function to escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Set up auto-refresh every 20 minutes (1200000 milliseconds)
    const refreshInterval = 20 * 60 * 1000; // 20 minutes in milliseconds
    setInterval(refreshRecentActivity, refreshInterval);

    // Also refresh on page visibility change (when user comes back to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshRecentActivity();
        }
    });
}

// Profile page functionality
let downloadsChart = null;
let apiRequestsChart = null;

function initProfilePage() {
    // Animate progress bars
    animateProgressBars();
}

function animateProgressBars() {
    document.querySelectorAll('.progress-bar').forEach(bar => {
        const width = bar.dataset.width;
        if (width) {
            setTimeout(() => {
                bar.style.width = width + '%';
            }, 100);
        }
    });
}

// Theme Management (moved from main.blade.php)
function initThemeManagement() {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function applyTheme(themePreference) {
        const html = document.documentElement;

        if (themePreference === 'system') {
            if (mediaQuery.matches) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        } else if (themePreference === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
    }

    function updateThemeButton(theme) {
        const themeIcon = document.getElementById('theme-icon');
        const themeLabel = document.getElementById('theme-label');
        const themeToggle = document.getElementById('theme-toggle');

        const icons = {
            'light': 'fa-sun',
            'dark': 'fa-moon',
            'system': 'fa-desktop'
        };
        const labels = {
            'light': 'Light',
            'dark': 'Dark',
            'system': 'System'
        };
        const titles = {
            'light': 'Light Mode',
            'dark': 'Dark Mode',
            'system': 'System Mode'
        };

        if (themeIcon) {
            themeIcon.classList.remove('fa-sun', 'fa-moon', 'fa-desktop');
            themeIcon.classList.add(icons[theme]);
        }
        if (themeLabel) {
            themeLabel.textContent = labels[theme];
        }
        if (themeToggle) {
            themeToggle.setAttribute('title', titles[theme]);
        }
    }

    // Get current theme from data attribute or localStorage
    const currentThemeElement = document.getElementById('current-theme-data');
    let currentTheme = currentThemeElement ? currentThemeElement.dataset.theme : 'light';
    const isAuthenticated = currentThemeElement ? currentThemeElement.dataset.authenticated === 'true' : false;

    if (!isAuthenticated) {
        currentTheme = localStorage.getItem('theme') || 'light';
    }

    // Listen for OS theme changes
    mediaQuery.addEventListener('change', () => {
        if (currentTheme === 'system') {
            applyTheme('system');
        }
    });

    // Listen for custom theme change events from sidebar
    document.addEventListener('themeChanged', function(e) {
        if (e.detail && e.detail.theme) {
            updateThemeButton(e.detail.theme);
            applyTheme(e.detail.theme);
            currentTheme = e.detail.theme;
        }
    });

    // Dark mode toggle
    const themeToggle = document.getElementById('theme-toggle');

    themeToggle?.addEventListener('click', function() {
        let nextTheme;

        if (currentTheme === 'light') {
            nextTheme = 'dark';
        } else if (currentTheme === 'dark') {
            nextTheme = 'system';
        } else {
            nextTheme = 'light';
        }

        applyTheme(nextTheme);
        updateThemeButton(nextTheme);

        if (isAuthenticated) {
            const updateThemeUrl = currentThemeElement ? currentThemeElement.dataset.updateUrl : '/profile/update-theme';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(updateThemeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ theme_preference: nextTheme })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      currentTheme = nextTheme;
                      document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: nextTheme } }));
                  }
              });
        } else {
            localStorage.setItem('theme', nextTheme);
            currentTheme = nextTheme;
            document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: nextTheme } }));
        }
    });

    // Mobile sidebar toggle
    document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar')?.classList.toggle('hidden');
    });
}

// Copy to Clipboard functionality - consolidated and optimized
function initCopyToClipboard() {
    // Unified copy function with visual feedback
    function copyToClipboard(text, button) {
        // Try modern Clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback(button);
            }).catch(function() {
                // Fallback for older browsers
                fallbackCopy(text, button);
            });
        } else {
            fallbackCopy(text, button);
        }
    }

    function fallbackCopy(text, button) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            showCopyFeedback(button);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
        document.body.removeChild(textarea);
    }

    function showCopyFeedback(button) {
        if (!button) return;
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-copy');
            icon.classList.add('fa-check');
        }
        if (button.classList) {
            button.classList.add('text-green-600');
        }
        setTimeout(function() {
            if (!button) return;
            if (icon) {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            }
            if (button.classList) {
                button.classList.remove('text-green-600');
            }
        }, 2000);
    }

    // Event delegation for copy buttons
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('.copy-btn');
        if (copyBtn) {
            const targetId = copyBtn.getAttribute('data-copy-target');
            const input = document.getElementById(targetId);
            if (input) {
                e.preventDefault();
                input.select();
                input.setSelectionRange(0, 99999);
                copyToClipboard(input.value, copyBtn);
            }
        }

        // API Token copy button (specific ID)
        const apiTokenBtn = e.target.id === 'copyApiToken' ? e.target : e.target.closest('#copyApiToken');
        if (apiTokenBtn) {
            const input = document.getElementById('apiTokenInput');
            if (input) {
                e.preventDefault();
                input.select();
                input.setSelectionRange(0, 99999);
                copyToClipboard(input.value, apiTokenBtn);
            }
        }
    });
}

// Verify User Modal functionality
function initVerifyUserModal() {
    let verifyForm = null;

    // Show verify modal
    window.showVerifyModal = function(event, form) {
        event.preventDefault();
        verifyForm = form;
        const modal = document.getElementById('verifyUserModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    };

    // Hide verify modal
    window.hideVerifyModal = function() {
        const modal = document.getElementById('verifyUserModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        verifyForm = null;
    };

    // Submit verify form
    window.submitVerifyForm = function() {
        if (verifyForm) {
            verifyForm.submit();
        } else {
            // Fallback: find form by data attribute
            const formId = document.querySelector('[data-show-verify-modal]')?.getAttribute('data-form-id');
            if (formId) {
                const form = document.querySelector(`form[action*="admin.verify"] input[value="${formId}"]`)?.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }
        hideVerifyModal();
    };

    // Event delegation for verify modal
    document.addEventListener('click', function(e) {
        if (e.target.hasAttribute('data-show-verify-modal') || e.target.closest('[data-show-verify-modal]')) {
            const element = e.target.hasAttribute('data-show-verify-modal') ? e.target : e.target.closest('[data-show-verify-modal]');
            const form = element.closest('form');
            if (form) {
                showVerifyModal(e, form);
            }
        }

        if (e.target.hasAttribute('data-close-verify-modal') || e.target.closest('[data-close-verify-modal]')) {
            e.preventDefault();
            hideVerifyModal();
        }

        if (e.target.hasAttribute('data-submit-verify-form') || e.target.closest('[data-submit-verify-form]')) {
            e.preventDefault();
            submitVerifyForm();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('verifyUserModal');
            if (modal && !modal.classList.contains('hidden')) {
                hideVerifyModal();
            }
        }
    });
}

// Flash Messages as Toast Notifications
function initFlashMessages() {
    const flashMessagesElement = document.getElementById('flash-messages-data');
    if (!flashMessagesElement) {
        return;
    }

    const flashMessages = JSON.parse(flashMessagesElement.dataset.messages || '{}');

    if (flashMessages.success && typeof showToast === 'function') {
        showToast(flashMessages.success, 'success');
    }

    if (flashMessages.error) {
        if (Array.isArray(flashMessages.error)) {
            flashMessages.error.forEach(error => {
                if (typeof showToast === 'function') {
                    showToast(error, 'error');
                }
            });
        } else if (typeof showToast === 'function') {
            showToast(flashMessages.error, 'error');
        }
    }

    if (flashMessages.warning && typeof showToast === 'function') {
        showToast(flashMessages.warning, 'warning');
    }

    if (flashMessages.info && typeof showToast === 'function') {
        showToast(flashMessages.info, 'info');
    }
}

// Initialize on DOMContentLoaded
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initThemeManagement();
            initFlashMessages();
        });
    } else {
        initThemeManagement();
        initFlashMessages();
    }
})();

// Tmux Edit - Remove Crap Releases Toggle
function toggleCrapTypes(value) {
    const container = document.getElementById('crap_types_container');
    if (container) {
        if (value === 'Custom') {
            container.style.display = 'block';
            // Add a slight animation effect
            setTimeout(() => {
                container.style.opacity = '1';
            }, 10);
        } else {
            container.style.opacity = '0.5';
            setTimeout(() => {
                container.style.display = 'none';
                container.style.opacity = '1';
            }, 200);
        }
    }
}

// Initialize tmux edit page functionality
function initTmuxEdit() {
    // Initialize crap types toggle on page load
    const checkedRadio = document.querySelector('input[name="fix_crap_opt"]:checked');
    if (checkedRadio) {
        toggleCrapTypes(checkedRadio.value);
    }

    // Add event listeners to all radio buttons
    document.querySelectorAll('input[name="fix_crap_opt"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleCrapTypes(this.value);
        });
    });
}

// Initialize on DOMContentLoaded if on tmux-edit page
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('crap_types_container')) {
            initTmuxEdit();
        }
    });
} else {
    if (document.getElementById('crap_types_container')) {
        initTmuxEdit();
    }
}

