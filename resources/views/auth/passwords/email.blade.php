@extends('layouts.guest')

@section('content')
<div class="min-h-screen auth-page flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo and Title -->
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-primary-600 dark:bg-primary-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-file-download text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-4xl font-extrabold text-gray-900 dark:text-white">
                Reset Password
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Enter your email to receive a password reset link
            </p>
        </div>

        <!-- Reset Password Card -->
        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <!-- Error Messages -->
                @if(isset($errors) && $errors->any())
                    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <ul class="text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Reset Form -->
                <form method="POST" action="{{ route('forgottenpassword') }}" class="space-y-6">
                    @csrf

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition {{ isset($errors) && $errors->has('email') ? 'border-red-500' : '' }}"
                                placeholder="your@email.com"
                            >
                        </div>
                        @if(isset($errors) && $errors->has('email'))
                            <p class="mt-2 text-sm text-red-600">{{ $errors->first('email') }}</p>
                        @endif
                    </div>

                    <!-- CAPTCHA (reCAPTCHA or Turnstile) -->
                    @if(\App\Support\CaptchaHelper::isEnabled())
                        <div class="flex justify-center">
                            {!! \App\Support\CaptchaHelper::display() !!}
                        </div>
                        @error(\App\Support\CaptchaHelper::getResponseFieldName())
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400 text-center">{{ $message }}</p>
                        @enderror
                    @endif

                    <!-- Submit Button -->
                    <div>
                        <x-button
                            type="submit"
                            class="w-full"
                            icon="fas fa-paper-plane"
                        >
                            Send Password Reset Link
                        </x-button>
                    </div>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="px-8 py-4 surface-panel-alt border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm">
                    <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-500 font-medium transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back to login
                    </a>

                    @if(Route::has('register'))
                        <a href="{{ route('register') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                            <i class="fas fa-user-plus mr-1"></i> Create an account
                        </a>
                    @endif
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
