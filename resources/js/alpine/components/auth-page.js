/**
 * Alpine.data('authPage') - Login/register page features
 * Alpine.data('otpInput') - 2FA OTP auto-submit
 */
import Alpine from '@alpinejs/csp';

Alpine.data('authPage', () => ({
    init() {
        // Auto-hide success messages after 5 seconds on login page
        if (window.location.pathname.includes('/login')) {
            setTimeout(() => {
                this.$el.querySelectorAll('.bg-green-50, .bg-blue-50').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        }
    }
}));

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
