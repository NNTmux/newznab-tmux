{{-- Toast Notification Component --}}

<style>
#toast-container .toast-notification {
    position: relative;
    width: 350px;
    margin-bottom: 10px;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: block;
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    animation: slideInRight 0.3s ease-out;
}

#toast-container .toast-notification.removing {
    animation: slideOutRight 0.3s ease-out;
}

.toast-notification.success {
    background-color: #10b981;
    border-left: 4px solid #059669;
}

.toast-notification.error {
    background-color: #ef4444;
    border-left: 4px solid #dc2626;
}

.toast-notification.info {
    background-color: #3b82f6;
    border-left: 4px solid #2563eb;
}

.toast-notification .toast-icon {
    display: inline-block;
    margin-right: 12px;
    font-size: 20px;
    vertical-align: middle;
}

.toast-notification .toast-message {
    display: inline-block;
    vertical-align: middle;
    max-width: 260px;
}

.toast-notification .toast-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    color: #ffffff;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    opacity: 0.8;
}

.toast-notification .toast-close:hover {
    opacity: 1;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style>

<script>
// Define showToast globally - immediately available
window.showToast = function(message, type) {
    type = type || 'success';

    console.log('showToast called:', message, type);

    var container = document.getElementById('toast-container');
    if (!container) {
        console.error('Toast container NOT found!');
        alert(message);
        return;
    }

    var toast = document.createElement('div');
    toast.className = 'toast-notification ' + type;

    var iconClass = type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    'fa-info-circle';

    toast.innerHTML =
        '<span class="toast-icon"><i class="fas ' + iconClass + '"></i></span>' +
        '<span class="toast-message">' + message + '</span>' +
        '<button class="toast-close" onclick="this.parentElement.remove()">×</button>';

    container.appendChild(toast);
    console.log('Toast added! Container children:', container.children.length);

    // Force a reflow to ensure animation triggers
    toast.offsetHeight;

    // Auto remove after 4 seconds
    setTimeout(function() {
        toast.classList.add('removing');
        setTimeout(function() {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 4000);
};

console.log('Toast system loaded');
</script>

