@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo and Title -->
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-blue-600 dark:bg-gray-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-file-download text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-4xl font-extrabold text-gray-900 dark:text-white">
                {{ config('app.name') }}
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Sign in to your account
            </p>
        </div>

        <!-- Login Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <!-- Session Messages -->
                @if(session('message'))
                    <div class="mb-4 p-4 rounded-lg {{ session('message_type') == 'success' ? 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700' : (session('message_type') == 'danger' ? 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700' : 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700') }}">
                        <div class="flex items-center">
                            <i class="fas {{ session('message_type') == 'success' ? 'fa-check-circle text-green-600 dark:text-green-400' : (session('message_type') == 'danger' ? 'fa-exclamation-circle text-red-600 dark:text-red-400' : 'fa-info-circle text-blue-600 dark:text-blue-400') }} mr-3"></i>
                            <span class="{{ session('message_type') == 'success' ? 'text-green-800 dark:text-green-200' : (session('message_type') == 'danger' ? 'text-red-800 dark:text-red-200' : 'text-blue-800 dark:text-blue-200') }}">
                                {{ session('message') }}
                            </span>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">There were some errors with your submission:</p>
                                <ul class="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

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
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input
                                id="username"
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('username') border-red-500 @enderror"
                                placeholder="Enter your username or email"
                            >
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('password') border-red-500 @enderror"
                                placeholder="Enter your password"
                            >
                            <button type="button" class="password-toggle-btn absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" data-field-id="password">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
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
                                class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded"
                            >
                            <label for="rememberme" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Remember me
                            </label>
                        </div>

                        @if(Route::has('forgottenpassword'))
                            <a href="{{ route('forgottenpassword') }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 transition">
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
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    @endif

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out"
                        >
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="px-8 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm">
                    <div class="flex flex-col sm:flex-row gap-3 items-center">
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium transition">
                                <i class="fas fa-user-plus mr-1"></i> Create an account
                            </a>
                        @endif

                        @if(Route::has('contact-us'))
                            <a href="{{ route('contact-us') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium transition">
                                <i class="fas fa-envelope mr-1"></i> Contact Us
                            </a>
                        @endif
                    </div>

                    <a href="{{ url('/') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                        <i class="fas fa-home mr-1"></i> Back to home
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
