import { escapeHtml } from './utils.js';

// Toast Notifications
export function initToastNotifications() {
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

// Flash Messages as Toast Notifications
export function initFlashMessages() {
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
