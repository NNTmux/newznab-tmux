import Alpine from '@alpinejs/csp';

Alpine.data('loginMode', () => ({
    supported: false,
    showPassword: false,

    init() {
        this.supported = typeof window.browserSupportsWebAuthn === 'function'
            && window.browserSupportsWebAuthn();

        const prefersPassword = this.$el.dataset.prefersPassword === '1';
        if (! this.supported || prefersPassword) {
            this.showPassword = true;
        }
    },

    usePassword() {
        this.showPassword = true;
    },

    usePasskey() {
        this.showPassword = false;
    },
}));
