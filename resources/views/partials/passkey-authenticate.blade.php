<div
    x-data="passkeyLogin"
    x-cloak
    data-options-url="{{ route('passkeys.authentication_options') }}"
    data-server-passkey-error="{{ session('authenticatePasskey::reason') === 'invalid_passkey' ? '1' : '0' }}"
    data-auto-prompt="{{ ($autoPromptPasskey ?? true) ? '1' : '0' }}"
    data-remember-default="{{ old('rememberme') ? '1' : '0' }}"
    data-captcha-enabled="{{ \App\Support\CaptchaHelper::isEnabled() ? '1' : '0' }}"
    data-captcha-field="{{ \App\Support\CaptchaHelper::isEnabled() ? \App\Support\CaptchaHelper::getResponseFieldName() : '' }}"
    class="mt-6"
>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        On managed/company devices, platform passkeys may be unavailable due to policy. Use a FIDO2 security key if prompted.
    </p>

    @if($message = session('authenticatePasskey::message'))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300" role="alert">
            {{ $message }}
        </div>
    @endif

    <form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}" class="mt-4">
        @csrf
        <input type="hidden" name="remember" x-ref="remember" value="0">
        <input type="hidden" name="start_authentication_response" x-ref="response" value="">
        <input type="hidden" name="cf-turnstile-response" x-ref="turnstileResponse" value="">
        <input type="hidden" name="g-recaptcha-response" x-ref="recaptchaResponse" value="">
    </form>

    <div class="mt-4 flex items-center">
        <input
            id="passkey-remember"
            x-model="remember"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:text-blue-400"
        >
        <label for="passkey-remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
            Remember me
        </label>
    </div>

    <button
        type="button"
        @click="authenticate()"
        :disabled="busy"
        class="mt-4 flex w-full items-center justify-center rounded-lg border border-blue-600 px-4 py-3 text-sm font-medium text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-400 dark:text-blue-300 dark:hover:bg-blue-900/30"
    >
        <i class="fas fa-fingerprint mr-2"></i>
        <span x-text="busy ? 'Waiting for passkey...' : 'Sign in with passkey'"></span>
    </button>

    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>

    <div
        x-show="showCreateHint"
        x-cloak
        class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-200"
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
                class="rounded-md border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-800 hover:bg-blue-100 dark:border-blue-600 dark:text-blue-200 dark:hover:bg-blue-900/40"
            >
                Use password login
            </button>
            @if(Route::has('register'))
                <a
                    href="{{ route('register') }}"
                    class="rounded-md border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-800 hover:bg-blue-100 dark:border-blue-600 dark:text-blue-200 dark:hover:bg-blue-900/40"
                >
                    Create account
                </a>
            @endif
        </div>
    </div>
</div>
