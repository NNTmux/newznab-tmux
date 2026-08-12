<div
    x-data="passkeyLogin"
    x-cloak
    data-options-url="{{ route('passkeys.authentication_options') }}"
    data-server-passkey-error="{{ session('authenticatePasskey::reason') === 'invalid_passkey' ? '1' : '0' }}"
    data-auto-prompt="{{ ($autoPromptPasskey ?? true) ? '1' : '0' }}"
    data-captcha-enabled="{{ \App\Support\CaptchaHelper::isEnabled() ? '1' : '0' }}"
    data-captcha-field="{{ \App\Support\CaptchaHelper::isEnabled() ? \App\Support\CaptchaHelper::getResponseFieldName() : '' }}"
    class="mt-6"
>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Saved browser and password-manager passkeys can appear from the username field. Security keys are still supported.
    </p>

    @if($message = session('authenticatePasskey::message'))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300" role="alert">
            {{ $message }}
        </div>
    @endif

    <form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}" class="mt-4">
        @csrf
        <input type="hidden" name="start_authentication_response" x-ref="response" value="">
        <input type="hidden" name="cf-turnstile-response" x-ref="turnstileResponse" value="">
        <input type="hidden" name="g-recaptcha-response" x-ref="recaptchaResponse" value="">
    </form>

    <div x-show="supportsAutofill" x-cloak class="mt-4">
        <label for="passkey-autofill" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Username or email
        </label>
        <input
            id="passkey-autofill"
            x-ref="browserPasskeyInput"
            type="text"
            autocomplete="username webauthn"
            inputmode="email"
            class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-3 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            placeholder="Choose a saved passkey"
        >
    </div>

    <button
        type="button"
        @click="authenticate()"
        :disabled="busy"
        class="mt-4 flex w-full items-center justify-center rounded-lg border border-primary-600 px-4 py-3 text-sm font-medium text-primary-700 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-primary-400 dark:text-primary-300 dark:hover:bg-primary-900/30"
    >
        <i class="fas fa-fingerprint mr-2"></i>
        <span x-text="busy ? 'Waiting for passkey...' : 'Sign in with passkey'"></span>
    </button>

    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

    <div
        x-show="showCreateHint"
        x-cloak
        class="mt-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-primary-800 dark:border-primary-700 dark:bg-primary-900/20 dark:text-primary-200"
    >
        <p>
            No passkey was found for this login on this device/browser.
        </p>
        <p class="mt-1">
            Sign in with your password first. After login, go to your profile security settings and create a passkey.
        </p>

        <div class="mt-2 flex flex-wrap gap-2">
            <button
                type="button"
                @click="$dispatch('use-password-login')"
                class="rounded-md border border-primary-300 px-3 py-1.5 text-xs font-medium text-primary-800 hover:bg-primary-100 dark:border-primary-600 dark:text-primary-200 dark:hover:bg-primary-900/40"
            >
                Use password login
            </button>
            @if(Route::has('register'))
                <a
                    href="{{ route('register') }}"
                    class="rounded-md border border-primary-300 px-3 py-1.5 text-xs font-medium text-primary-800 hover:bg-primary-100 dark:border-primary-600 dark:text-primary-200 dark:hover:bg-primary-900/40"
                >
                    Create account
                </a>
            @endif
        </div>
    </div>
</div>
