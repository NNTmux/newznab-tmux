/**
 * Form-related functions extracted from csp-safe.js
 */

import { escapeHtml } from './utils.js';

// Select redirects
export function initSelectRedirects() {
    // Already handled in event delegation
}

// Logout forms
export function initLogoutForms() {
    // Already handled in event delegation
}

// Password visibility toggle
window.togglePasswordVisibility = function(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-eye');

    if (!field || !icon) return;

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
};

/**
 * Initialize password visibility toggles for all password fields with toggle buttons
 */
export function initPasswordVisibilityToggles() {
    // Add click event listeners to all password toggle buttons
    document.querySelectorAll('.password-toggle-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const fieldId = this.getAttribute('data-field-id');
            if (fieldId) {
                window.togglePasswordVisibility(fieldId);
            }
        });
    });
}

// Regex Management Functions
export function initRegexManagement() {
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

        // Handle release delete buttons
        if (e.target.hasAttribute('data-delete-release') || e.target.closest('[data-delete-release]')) {
            const element = e.target.hasAttribute('data-delete-release') ? e.target : e.target.closest('[data-delete-release]');
            const id = element.getAttribute('data-delete-release');
            const deleteUrl = element.getAttribute('data-delete-url');

            showConfirm({
                title: 'Delete Release',
                message: 'Are you sure you want to delete this release? This action cannot be undone.',
                type: 'danger',
                confirmText: 'Delete',
                onConfirm: function() {
                    deleteRelease(id, deleteUrl, element);
                }
            });
            e.preventDefault();
        }
    });

    // Delete release function
    window.deleteRelease = function(id, deleteUrl, element) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!csrfToken) {
            console.error('CSRF token not found');
            if (typeof showToast === 'function') {
                showToast('Security token not found. Please refresh the page.', 'error');
            }
            return;
        }

        console.log('Deleting release:', id, 'URL:', deleteUrl);

        if (typeof showToast === 'function') {
            showToast('Deleting release...', 'info');
        }

        fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            if (response.ok) {
                const row = element.closest('tr');
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
                if (typeof showToast === 'function') {
                    showToast('Release deleted successfully', 'success');
                } else {
                    showMessage('Release deleted successfully', 'success');
                }
            } else {
                return response.text().then(text => {
                    console.error('Delete failed with status:', response.status, 'Response:', text);
                    if (typeof showToast === 'function') {
                        showToast('Error deleting release: ' + response.status, 'error');
                    } else {
                        showMessage('Error deleting release', 'error');
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error deleting release:', error);
            if (typeof showToast === 'function') {
                showToast('Error deleting release: ' + error.message, 'error');
            } else {
                showMessage('Error deleting release', 'error');
            }
        });
    };
}

// Image Fallbacks
export function initImageFallbacks() {
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
