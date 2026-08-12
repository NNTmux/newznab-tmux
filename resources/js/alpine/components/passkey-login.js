import Alpine from '@alpinejs/csp';

Alpine.data('passkeyLogin', () => ({
    supported: false,
    supportsAutofill: false,
    busy: false,
    browserPasskeyPending: false,
    browserPasskeyStarted: false,
    error: '',
    showCreateHint: false,
    hasAutoPrompted: false,

    init() {
        this.supported = typeof window.browserSupportsWebAuthn === 'function'
            && window.browserSupportsWebAuthn();
        // If backend already reported an invalid passkey login attempt,
        // immediately show the "sign in first, then create passkey" guidance.
        this.showCreateHint = this.$el.dataset.serverPasskeyError === '1';

        const shouldAutoPrompt = this.$el.dataset.autoPrompt === '1';
        void this.detectAutofillSupport(shouldAutoPrompt);
    },

    async detectAutofillSupport(shouldAutoPrompt) {
        this.supportsAutofill = typeof window.browserSupportsWebAuthnAutofill === 'function'
            && this.supported
            && await window.browserSupportsWebAuthnAutofill();

        if (this.supported && shouldAutoPrompt && !this.showCreateHint) {
            window.requestAnimationFrame(() => {
                if (this.hasAutoPrompted || this.busy || this.browserPasskeyStarted) {
                    return;
                }

                this.hasAutoPrompted = true;
                if (this.supportsAutofill) {
                    void this.startBrowserPasskeyFallback({ silent: true });
                    return;
                }

                if (shouldAutoPrompt) {
                    void this.authenticate();
                }
            });
        }
    },

    async authenticate() {
        this.error = '';
        this.showCreateHint = false;
        this.busy = true;

        try {
            const optionsJson = await this.loadAuthenticationOptions();
            const startAuthenticationResponse = await window.startAuthentication({
                optionsJSON: optionsJson,
            });

            this.submitAuthentication(startAuthenticationResponse);
        } catch (error) {
            if (this.shouldOfferBrowserPasskeyFallback(error)) {
                this.error = 'Select a saved browser or app passkey from the username field.';
                this.focusBrowserPasskeyInput();
                void this.startBrowserPasskeyFallback({ silent: true });
                this.busy = false;

                return;
            }

            this.error = error instanceof Error
                ? error.message
                : 'Passkey authentication failed.';

            // Common browser errors when no passkey exists for this account/device.
            if (error instanceof Error && ['NotAllowedError', 'InvalidStateError', 'SecurityError'].includes(error.name)) {
                this.showCreateHint = true;
            }

            this.busy = false;
        }
    },

    async startBrowserPasskeyFallback({ silent = false } = {}) {
        if (!this.supported || !this.supportsAutofill || this.browserPasskeyStarted) {
            return;
        }

        this.browserPasskeyStarted = true;
        this.browserPasskeyPending = true;

        if (!silent) {
            this.error = '';
        }

        try {
            const optionsJson = await this.loadAuthenticationOptions();
            const startAuthenticationResponse = await window.startAuthentication({
                optionsJSON: optionsJson,
                useBrowserAutofill: true,
            });

            this.submitAuthentication(startAuthenticationResponse);
        } catch (error) {
            this.browserPasskeyStarted = false;
            this.browserPasskeyPending = false;

            if (this.isCeremonyAbort(error)) {
                return;
            }

            if (!silent) {
                this.error = error instanceof Error
                    ? error.message
                    : 'Browser passkey sign-in failed.';
            }
        }
    },

    async loadAuthenticationOptions() {
        this.copyCaptchaResponse();

        const rawOptionsUrl = this.$el.dataset.optionsUrl;
        const optionsUrl = (!rawOptionsUrl || rawOptionsUrl === 'undefined')
            ? '/passkeys/authentication-options'
            : rawOptionsUrl;
        const optionsResponse = await fetch(optionsUrl, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!optionsResponse.ok) {
            throw new Error('Unable to load passkey authentication options.');
        }

        return optionsResponse.json();
    },

    submitAuthentication(startAuthenticationResponse) {
        this.$refs.response.value = JSON.stringify(startAuthenticationResponse);
        document.getElementById('passkey-login-form')?.submit();
    },

    shouldOfferBrowserPasskeyFallback(error) {
        if (!this.supportsAutofill || this.browserPasskeyStarted) {
            return false;
        }

        return error instanceof Error
            && ['NotAllowedError', 'InvalidStateError', 'SecurityError', 'UnknownError'].includes(error.name);
    },

    focusBrowserPasskeyInput() {
        window.requestAnimationFrame(() => {
            this.$refs.browserPasskeyInput?.focus();
        });
    },

    isCeremonyAbort(error) {
        return error instanceof Error
            && (error.name === 'AbortError' || error.code === 'ERROR_CEREMONY_ABORTED');
    },

    copyCaptchaResponse() {
        const turnstileValue = this.getFieldValue('cf-turnstile-response');
        const recaptchaValue = this.getFieldValue('g-recaptcha-response');

        if (this.$refs.turnstileResponse) {
            this.$refs.turnstileResponse.value = turnstileValue;
        }

        if (this.$refs.recaptchaResponse) {
            this.$refs.recaptchaResponse.value = recaptchaValue;
        }
    },

    getFieldValue(fieldName) {
        const candidates = document.querySelectorAll(`[name="${fieldName}"]`);

        for (const field of candidates) {
            if (field === this.$refs.turnstileResponse || field === this.$refs.recaptchaResponse) {
                continue;
            }

            if (typeof field.value === 'string' && field.value.trim() !== '') {
                return field.value.trim();
            }
        }

        return '';
    },
}));
