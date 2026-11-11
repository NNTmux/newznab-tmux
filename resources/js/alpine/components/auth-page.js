/**
 * Alpine.data('authPage') - Login/register page features
 * Alpine.data('otpInput') - 2FA OTP auto-submit
 */
import Alpine from '@alpinejs/csp';

Alpine.data('otpInput', () => ({
    value: '',

    onInput() {
        // Remove non-numeric
        this.value = this.value.replace(/[^0-9]/g, '');
        // Auto-submit at 6 digits
        if (this.value.length === 6) {
            setTimeout(() => {
                const form = this.$el.closest('form');
                if (form) form.submit();
            }, 300);
        }
    },

    init() {
        this.$nextTick(() => this.$el.focus());
    }
}));

// Document-level delegation for auth pages without x-data
(function() {
    if (window.location.pathname.includes('/login')) {
        setTimeout(function() {
            document.querySelectorAll('.bg-success-50, .bg-primary-50').forEach(function(el) {
                el.style.transition = 'opacity 0.5s ease-out'; el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            });
        }, 5000);
    }
    var otp = document.getElementById('one_time_password');
    if (otp && !otp.closest('[x-data]')) {
        otp.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); if (this.value.length === 6) setTimeout(function() { otp.form.submit(); }, 300); });
        otp.focus();
    }
})();
