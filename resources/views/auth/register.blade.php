@extends('layouts.guest')

@section('title', __('Create an account'))

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
                Create your account
            </h1>
        </div>

        <!-- Register Card -->
        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-5 py-5 sm:px-8 sm:py-6">
                <!-- Registration Status Error Message -->
                @if(!empty($error) && $showregister == 0)
                    <div class="p-6 rounded-lg bg-warning-50 dark:bg-warning-900/30 border border-warning-200 dark:border-warning-700">
                        <div class="flex items-start">
                            <i aria-hidden="true" class="fas fa-info-circle text-warning-600 dark:text-warning-400 mr-3 mt-0.5 text-xl"></i>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-warning-800 dark:text-warning-200 mb-2">Registration Not Available</h3>
                                <p class="text-sm text-warning-700 dark:text-warning-300">{{ $error }}</p>
                                @if(strpos($error, 'invite only') !== false)
                                    <p class="mt-3 text-sm text-warning-700 dark:text-warning-300">
                                        <i aria-hidden="true" class="fas fa-envelope mr-1"></i>
                                        You need a valid invitation link to register. Please check your email or contact an existing member.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty($notice))
                    <div class="mb-4 p-4 rounded-lg bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700">
                        <div class="flex items-start">
                            <i aria-hidden="true" class="fas fa-circle-info text-link dark:text-primary-400 mr-3 mt-0.5"></i>
                            <p class="text-sm text-primary-800 dark:text-primary-200">{{ $notice }}</p>
                        </div>
                    </div>
                @endif

                <!-- Validation Error Messages -->
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

                <!-- Register Form -->
                @if($showregister == 1)
                <form method="POST" action="{{ route('register.post') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="action" value="submit">

                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Username
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
                                placeholder="Choose a username"
                            >
                        </div>
                        @error('username')
                            <p id="username-error" class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i aria-hidden="true" class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                                autocomplete="email"
                                value="{{ old('email', $email ?? '') }}"
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('email') border-danger-500 @enderror"
                                placeholder="your@email.com"
                            >
                        </div>
                        @error('email')
                            <p id="email-error" class="mt-2 text-sm text-danger-600">{{ $message }}</p>
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
                                autocomplete="new-password"
                                required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('password') border-danger-500 @enderror"
                                placeholder="Create a strong password"
                            >
                            <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" @click="toggle()" aria-label="Show password" :aria-label="label()" aria-controls="password" :aria-pressed="visible">
                                <i class="fas" :class="iconClass()" aria-hidden="true" id="password-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <p id="password-error" class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                            <p class="font-medium">Password requirements:</p>
                            <ul class="list-disc list-inside pl-2 space-y-0.5">
                                <li>At least 8 characters long</li>
                                <li>Contains uppercase and lowercase letters</li>
                                <li>Contains at least one number</li>
                                <li>Contains at least one symbol</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative" x-data="passwordToggle">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i aria-hidden="true" class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input
                                id="password_confirmation"
                                x-ref="field"
                                type="password"
                                name="password_confirmation"
                                autocomplete="new-password"
                                required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                                placeholder="Confirm your password"
                            >
                            <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" @click="toggle()" aria-label="Show password" :aria-label="label()" aria-controls="password_confirmation" :aria-pressed="visible">
                                <i class="fas" :class="iconClass()" aria-hidden="true" id="password_confirmation-eye"></i>
                            </button>
                        </div>
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

                    <!-- Terms and Conditions -->
                    <div class="flex items-start">
                        <input
                            id="terms"
                            name="terms"
                            type="checkbox"
                            required
                            class="h-4 w-4 text-link dark:text-primary-400 focus:ring-primary-500 border-gray-300 dark:border-gray-600 rounded mt-1"
                        >
                        <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            I agree to the
                            <a href="{{ url('/terms-and-conditions') }}" target="_blank" rel="noopener noreferrer" class="text-link dark:text-primary-400 hover:text-primary-500">Terms and Conditions</a>
                            and
                            <a href="{{ url('/privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="text-link dark:text-primary-400 hover:text-primary-500">Privacy Policy</a>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out"
                        >
                            <i aria-hidden="true" class="fas fa-user-plus mr-2"></i>
                            Create Account
                        </button>
                    </div>
                </form>
                @endif
            </div>

            <!-- Card Footer -->
            <div class="border-t border-gray-200 surface-panel-alt px-5 py-4 dark:border-gray-700 sm:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm">
                    <a href="{{ route('login') }}" class="text-link dark:text-primary-400 hover:text-primary-500 font-medium transition">
                        <i aria-hidden="true" class="fas fa-sign-in-alt mr-1"></i> Already have an account? Sign in
                    </a>

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
