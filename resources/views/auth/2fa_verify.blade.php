@extends('layouts.guest')

@section('title', __('Two-factor verification'))

@section('content')
<div class="min-h-screen auth-page flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo and Title -->
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-accent dark:bg-accent-hover rounded-full flex items-center justify-center shadow-lg">
                    <i aria-hidden="true" class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
            </a>
            <h1 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                Two-Factor Authentication
            </h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Enter the code from your authenticator app
            </p>
        </div>

        <!-- 2FA Verification Card -->
        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                @if($errors->any())
                    <div class="mb-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-900/30 border border-danger-200 dark:border-danger-700">
                        <div class="flex items-start">
                            <i aria-hidden="true" class="fas fa-exclamation-circle text-danger-600 dark:text-danger-400 mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-danger-800 dark:text-danger-200">There were some errors:</p>
                                <ul class="mt-2 text-sm text-danger-700 dark:text-danger-300 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mb-6">
                    <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg p-4">
                        <div class="flex items-start">
                            <i aria-hidden="true" class="fas fa-info-circle text-link dark:text-primary-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-primary-800 dark:text-primary-200">
                                <p class="font-medium mb-1">Security Verification Required</p>
                                <p>Open your authenticator app and enter the 6-digit verification code to complete your login.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2FA Form -->
                <form method="POST" action="{{ route('2fa.post') }}" class="space-y-6">
                    @csrf

                    <!-- Verification Code Input -->
                    <div>
                        <label for="one_time_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Authentication Code
                        </label>
                        <input
                            x-data="otpInput"
                            x-model="value"
                            @input="onInput()"
                            type="text"
                            name="one_time_password"
                            id="one_time_password"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required
                            autofocus
                            placeholder="000000"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-center text-2xl tracking-widest font-mono bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                        >
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
                            Enter the 6-digit code from your authenticator app
                        </p>
                    </div>

                    <!-- Trust Device Checkbox -->
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            name="trust_device"
                            id="trust_device"
                            value="1"
                            class="h-4 w-4 text-link focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 checked:bg-accent dark:checked:bg-accent"
                        >
                        <label for="trust_device" class="ml-2 block text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            Trust this device for 30 days
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-accent hover:bg-accent-hover dark:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200"
                        >
                            <i aria-hidden="true" class="fas fa-sign-in-alt mr-2"></i>
                            Verify and Continue
                        </button>
                    </div>
                </form>

                <!-- Back to Login Link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 transition-colors">
                        <i aria-hidden="true" class="fas fa-arrow-left mr-1"></i>
                        Back to login
                    </a>
                </div>
            </div>

            <!-- Help Section -->
            <div class="surface-panel-alt px-8 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-600 dark:text-gray-400">
                    <p class="mb-2">
                        <i aria-hidden="true" class="fas fa-question-circle mr-1"></i>
                        <strong>Can't access your authenticator?</strong>
                    </p>
                    <p>
                        If you've lost access to your authentication device, please contact your administrator for assistance.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

