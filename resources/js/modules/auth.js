/**
 * Auth pages module
 * Extracted from csp-safe.js
 */

// Auth pages functionality
export function initAuthPages() {
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
