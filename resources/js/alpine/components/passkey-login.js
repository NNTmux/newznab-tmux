import Alpine from '@alpinejs/csp';

Alpine.data('passkeyLogin', () => ({
    supported: false,
    busy: false,
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
        if (this.supported && shouldAutoPrompt && !this.showCreateHint) {
            window.requestAnimationFrame(() => {
                if (this.hasAutoPrompted || this.busy) {
                    return;
                }

                this.hasAutoPrompted = true;
                void this.authenticate();
            });
        }
    },

    async authenticate() {
        this.error = '';
        this.showCreateHint = false;
        this.busy = true;

        try {
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

            const optionsJson = await optionsResponse.json();
            const startAuthenticationResponse = await window.startAuthentication({
                optionsJSON: optionsJson,
            });

            this.$refs.response.value = JSON.stringify(startAuthenticationResponse);
            document.getElementById('passkey-login-form')?.submit();
        } catch (error) {
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
