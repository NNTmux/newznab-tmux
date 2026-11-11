@extends('layouts.guest')

@section('title', __('Sign in'))

@section('content')
<div class="min-h-dvh auth-page flex items-start justify-center px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
    <div class="w-full max-w-md space-y-6 sm:space-y-8">
        <!-- Logo and Title -->
        <div class="text-center">
            <a aria-label="Home" href="{{ url('/') }}" class="mb-3 inline-flex items-center justify-center sm:mb-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-accent shadow-lg dark:bg-accent-hover sm:h-16 sm:w-16">
                    <i aria-hidden="true" class="fas fa-file-download text-2xl text-white sm:text-3xl"></i>
                </div>
            </a>
            <p class="mt-3 text-3xl font-extrabold text-gray-900 dark:text-white sm:mt-4 sm:text-4xl">
                {{ config('app.name') }}
            </p>
            <h1 class="mt-2 text-base font-medium text-gray-700 dark:text-gray-300">
                Sign in to your account
            </h1>
        </div>

        <!-- Login Card -->
        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div
                x-data="loginMode"
                x-on:use-password-login.stop="usePassword()"
                data-prefers-password="{{ $errors->any() ? '1' : '0' }}"
                class="px-5 py-5 sm:px-8 sm:py-6"
            >
                @if($errors->any())
                    <div role="alert" class="mb-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-900/30 border border-danger-200 dark:border-danger-700">
                        <div class="flex items-start">
                            <i aria-hidden="true" class="fas fa-exclamation-circle text-danger-600 dark:text-danger-400 mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-danger-800 dark:text-danger-200">There were some errors with your submission:</p>
                                <ul class="mt-2 text-sm text-danger-700 dark:text-danger-300 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div x-show="!showPassword" x-cloak>
                    @include('partials.passkey-authenticate', [
                        'autoPromptPasskey' => ! $errors->any() && ! session()->has('authenticatePasskey::message'),
                    ])

                    <button
                        type="button"
                        x-show="supported"
                        @click="usePassword()"
                        class="mt-4 inline-flex items-center text-sm font-medium text-link transition hover:text-primary-500 dark:text-primary-400"
                    >
                        Sign in with password instead
                        <i aria-hidden="true" class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>

                {{-- Without JavaScript Alpine never removes x-cloak, so reveal the password form (passkeys need scripts anyway). --}}
                <noscript>
                    <style nonce="{{ csp_nonce() }}">#login-password-panel[x-cloak] { display: block !important; }</style>
                </noscript>
                <div id="login-password-panel" x-show="showPassword" x-cloak>
                    <button
                        type="button"
                        x-show="supported"
                        @click="usePasskey()"
                        class="mb-4 inline-flex items-center text-sm font-medium text-link transition hover:text-primary-500 dark:text-primary-400"
                    >
                        <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i>
                        Sign in with passkey
                    </button>

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('login') }}" class="space-y-6">
                        @csrf

                        <!-- Username/Email Field -->
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Username or Email
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i aria-hidden="true" class="fas fa-user text-gray-400"></i>
                                </div>
                                <input
                                    id="username"
                                    type="text"
                                    name="username"
                                    @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                                    autocomplete="username"
                                    value="{{ old('username') }}"
                                    required
                                    autofocus
                                    class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('username') border-danger-500 @enderror"
                                    placeholder="Enter your username or email"
                                >
                            </div>
                            @error('username')
                                <p id="username-error" class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Password
                            </label>
                            <div class="relative" x-data="passwordToggle">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i aria-hidden="true" class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input
                                    id="password"
                                    x-ref="field"
                                    type="password"
                                    name="password"
                                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                                    autocomplete="current-password"
                                    required
                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('password') border-danger-500 @enderror"
                                    placeholder="Enter your password"
                                >
                                <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" @click="toggle()" aria-label="Show password" :aria-label="label()" aria-controls="password" :aria-pressed="visible">
                                    <i class="fas" :class="iconClass()" aria-hidden="true" id="password-eye"></i>
                                </button>
                            </div>
                            @error('password')
                                <p id="password-error" class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input
                                    id="rememberme"
                                    name="rememberme"
                                    type="checkbox"
                                    {{ old('rememberme') ? 'checked' : '' }}
                                    class="h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded"
                                >
                                <label for="rememberme" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Remember me
                                </label>
                            </div>

                            @if(Route::has('forgottenpassword'))
                                <a href="{{ route('forgottenpassword') }}" class="text-sm font-medium text-link dark:text-primary-400 hover:text-primary-500 transition">
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <!-- CAPTCHA (reCAPTCHA or Turnstile) -->
                        @if(\App\Support\CaptchaHelper::isEnabled())
                            <div>
                                {!! \App\Support\CaptchaHelper::display() !!}
                            </div>
                            @error(\App\Support\CaptchaHelper::getResponseFieldName())
                                <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        @endif

                        <!-- Submit Button -->
                        <div>
                            <button
                                type="submit"
                                class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out"
                            >
                                <i aria-hidden="true" class="fas fa-sign-in-alt mr-2"></i>
                                Sign In
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card Footer -->
            <div class="border-t border-gray-200 surface-panel-alt px-5 py-4 dark:border-gray-700 sm:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm">
                    <div class="flex flex-col sm:flex-row gap-3 items-center">
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="text-link dark:text-primary-400 hover:text-primary-500 font-medium transition">
                                <i aria-hidden="true" class="fas fa-user-plus mr-1"></i> Create an account
                            </a>
                        @endif

                        @if(Route::has('contact-us'))
                            <a href="{{ route('contact-us') }}" class="text-link dark:text-primary-400 hover:text-primary-500 font-medium transition">
                                <i aria-hidden="true" class="fas fa-envelope mr-1"></i> Contact Us
                            </a>
                        @endif
                    </div>

                    <a href="{{ url('/') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                        <i aria-hidden="true" class="fas fa-home mr-1"></i> Back to home
                    </a>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <p class="text-center text-sm text-gray-600 dark:text-gray-400">
            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</div>

@endsection
