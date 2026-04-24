import Alpine from '@alpinejs/csp';

Alpine.data('passkeyManage', () => ({
    supported: false,
    busy: false,
    name: '',
    error: '',
    success: '',
    passkeys: [],

    init() {
        this.supported = typeof window.browserSupportsWebAuthn === 'function'
            && window.browserSupportsWebAuthn();

        const rawPasskeys = this.$el.dataset.passkeys;
        if (rawPasskeys) {
            try {
                this.passkeys = JSON.parse(rawPasskeys);
            } catch (_error) {
                this.passkeys = [];
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
            const optionsUrl = this.$el.dataset.optionsUrl || '/passkeys/register-options';
            const storeUrl = this.$el.dataset.storeUrl || '/passkeys';

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
        if (!window.confirm('Delete this passkey? This device will no longer work for passkey login.')) {
            return;
        }

        this.error = '';
        this.success = '';

        try {
            const destroyUrl = `${this.$el.dataset.destroyBaseUrl}/${passkeyId}`;
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
