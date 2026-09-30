import Alpine from '@alpinejs/csp';

Alpine.data('passkeyManage', () => ({
    supported: false,
    platformAvailable: false,
    platformChecked: false,
    busy: false,
    name: '',
    error: '',
    success: '',
    passkeys: [],
    optionsUrl: '/passkeys/register-options',
    storeUrl: '/passkeys',
    destroyBaseUrl: '/passkeys',
    confirmPasswordUrl: '/confirm-password',

    init() {
        this.supported = typeof window.browserSupportsWebAuthn === 'function'
            && window.browserSupportsWebAuthn();
        void this.detectPlatformAuthenticator();

        // Capture URLs from the root element's data attributes once, so we don't
        // depend on $el inside event handlers (which may resolve to the triggering
        // element when using the Alpine CSP build).
        const root = this.$root || this.$el;
        if (root && root.dataset) {
            if (root.dataset.confirmPasswordUrl) {
                this.confirmPasswordUrl = root.dataset.confirmPasswordUrl;
            }
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

    async detectPlatformAuthenticator() {
        this.platformAvailable = typeof window.platformAuthenticatorIsAvailable === 'function'
            && await window.platformAuthenticatorIsAvailable();
        this.platformChecked = true;
    },

    isOperaOnWindows() {
        const userAgent = window.navigator?.userAgent || '';
        const platform = window.navigator?.platform || '';

        return (userAgent.includes('OPR/') || userAgent.includes('Opera'))
            && (userAgent.includes('Windows') || platform.startsWith('Win'));
    },

    async createPasskey() {
        return this.createBrowserPasskey();
    },

    async createBrowserPasskey() {
        if (this.browserPasskeyBlocked()) {
            this.error = 'Opera on Windows is routing passkey creation through Windows Security and cannot create a browser/app passkey here. Use Edge/Chrome with Windows Hello or a password-manager passkey provider, or use the USB security key button.';
            return;
        }

        return this.createPasskeyWithMode('browser');
    },

    browserPasskeyBlocked() {
        return this.isOperaOnWindows();
    },

    browserPasskeyNotice() {
        if (this.browserPasskeyBlocked()) {
            return 'Opera on Windows routes browser/app passkey creation through Windows Security on this setup. Use Edge/Chrome with Windows Hello or a password-manager passkey provider, or use the USB security key button.';
        }

        if (this.platformChecked && !this.platformAvailable) {
            return 'This browser did not report a built-in platform passkey provider. You can still try browser/app passkey creation; if it fails, enable Windows Hello or a password-manager passkey provider, or use the USB security key button.';
        }

        return '';
    },

    async createSecurityKeyPasskey() {
        return this.createPasskeyWithMode('security-key');
    },

    async createPasskeyWithMode(mode) {
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

            const registration = mode === 'security-key'
                ? await window.startRegistration({ optionsJSON: this.withSecurityKeyHint(options) })
                : await this.startBrowserPasskeyRegistration(options);

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

    async startBrowserPasskeyRegistration(options) {
        return window.startRegistration({ optionsJSON: this.withBrowserPasskeyHints(options) });
    },

    withBrowserPasskeyHints(options) {
        const normalized = this.cloneOptions(options);
        normalized.hints = ['client-device'];
        normalized.authenticatorSelection = {
            ...normalized.authenticatorSelection,
            authenticatorAttachment: 'platform',
            residentKey: normalized.authenticatorSelection?.residentKey || 'preferred',
            userVerification: normalized.authenticatorSelection?.userVerification || 'preferred',
        };

        if (normalized.publicKey) {
            normalized.publicKey.hints = ['client-device'];
            normalized.publicKey.authenticatorSelection = {
                ...normalized.publicKey.authenticatorSelection,
                authenticatorAttachment: 'platform',
                residentKey: normalized.publicKey.authenticatorSelection?.residentKey || 'preferred',
                userVerification: normalized.publicKey.authenticatorSelection?.userVerification || 'preferred',
            };
        }

        return normalized;
    },

    withSecurityKeyHint(options) {
        const normalized = this.cloneOptions(options);
        normalized.hints = ['security-key', 'client-device', 'hybrid'];
        normalized.authenticatorSelection = {
            ...normalized.authenticatorSelection,
            authenticatorAttachment: 'cross-platform',
        };

        if (normalized.publicKey) {
            normalized.publicKey.hints = ['security-key', 'client-device', 'hybrid'];
            normalized.publicKey.authenticatorSelection = {
                ...normalized.publicKey.authenticatorSelection,
                authenticatorAttachment: 'cross-platform',
            };
        }

        return normalized;
    },

    cloneOptions(options) {
        return JSON.parse(JSON.stringify(options));
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
        if (error?.response?.status === 423) {
            window.location.assign(this.confirmPasswordUrl);
        } else if (error?.response?.status === 403 && error.response.data?.redirect) {
            window.location.assign(error.response.data.redirect);
        }

        if (error?.response?.data?.message) {
            return error.response.data.message;
        }

        if (error instanceof Error) {
            return error.message;
        }

        return 'Something went wrong. Please try again.';
    },
}));
