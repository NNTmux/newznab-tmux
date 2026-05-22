import Alpine from '@alpinejs/csp';

Alpine.data('passkeyManage', () => ({
    supported: false,
    busy: false,
    name: '',
    error: '',
    success: '',
    passkeys: [],
    optionsUrl: '/passkeys/register-options',
    storeUrl: '/passkeys',
    destroyBaseUrl: '/passkeys',

    init() {
        this.supported = typeof window.browserSupportsWebAuthn === 'function'
            && window.browserSupportsWebAuthn();

        // Capture URLs from the root element's data attributes once, so we don't
        // depend on $el inside event handlers (which may resolve to the triggering
        // element when using the Alpine CSP build).
        const root = this.$root || this.$el;
        if (root && root.dataset) {
            if (root.dataset.optionsUrl) {
                this.optionsUrl = root.dataset.optionsUrl;
            }
            if (root.dataset.storeUrl) {
                this.storeUrl = root.dataset.storeUrl;
            }
            if (root.dataset.destroyBaseUrl) {
                this.destroyBaseUrl = root.dataset.destroyBaseUrl;
            }

            const rawPasskeys = root.dataset.passkeys;
            if (rawPasskeys) {
                try {
                    this.passkeys = JSON.parse(rawPasskeys);
                } catch (_error) {
                    this.passkeys = [];
                }
            }
        }
    },

    async createPasskey() {
        if (!this.supported) {
            this.error = 'Your browser does not support passkeys.';
            return;
        }

        this.busy = true;
        this.error = '';
        this.success = '';

        try {
            const optionsUrl = this.optionsUrl;
            const storeUrl = this.storeUrl;

            const optionsResponse = await window.axios.post(optionsUrl, {
                name: this.name,
            });
            const options = optionsResponse.data?.options;

            const registration = await window.startRegistration({ optionsJSON: options });

            const storeResponse = await window.axios.post(storeUrl, {
                name: this.name,
                passkey: JSON.stringify(registration),
            });

            if (!storeResponse.data?.ok) {
                throw new Error(storeResponse.data?.message || 'Could not store passkey.');
            }

            this.passkeys.unshift(storeResponse.data.passkey);
            this.success = 'Passkey created successfully.';
            this.name = '';
        } catch (error) {
            this.error = this.extractErrorMessage(error);
        } finally {
            this.busy = false;
        }
    },

    async deletePasskey(passkeyId) {
        const passkey = this.passkeys.find((p) => p.id === passkeyId);
        const passkeyName = passkey?.name ? `"${passkey.name}"` : 'this passkey';

        const confirmed = typeof window.showConfirm === 'function'
            ? await window.showConfirm({
                title: 'Delete passkey?',
                message: `Are you sure you want to delete ${passkeyName}?`,
                details: 'This device will no longer be able to sign in with a passkey. You can register it again later.',
                type: 'danger',
                confirmText: 'Delete passkey',
                cancelText: 'Cancel',
            })
            : window.confirm('Delete this passkey? This device will no longer work for passkey login.');

        if (!confirmed) {
            return;
        }

        this.error = '';
        this.success = '';

        try {
            const destroyUrl = `${this.destroyBaseUrl}/${passkeyId}`;
            const response = await window.axios.delete(destroyUrl);

            if (!response.data?.ok) {
                throw new Error('Could not delete passkey.');
            }

            this.passkeys = this.passkeys.filter((passkey) => passkey.id !== passkeyId);
            this.success = 'Passkey deleted successfully.';
        } catch (error) {
            this.error = this.extractErrorMessage(error);
        }
    },

    formatDate(value) {
        if (!value) {
            return 'Unknown';
        }

        return new Date(value).toLocaleString();
    },

    formatLastUsed(value) {
        if (!value) {
            return 'Not used yet';
        }

        return this.formatDate(value);
    },

    extractErrorMessage(error) {
        if (error?.response?.data?.message) {
            return error.response.data.message;
        }

        if (error instanceof Error) {
            return error.message;
        }

        return 'Something went wrong. Please try again.';
    },
}));
