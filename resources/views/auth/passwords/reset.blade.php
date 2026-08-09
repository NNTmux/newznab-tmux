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
            <h2 class="mt-4 text-4xl font-extrabold text-gray-900 dark:text-gray-100">
                Set New Password
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Choose a strong password for your account
            </p>
        </div>

        <!-- Reset Password Card -->
        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <!-- Error Messages -->
                @if($errors->any())
                    <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/30 dark:border-red-700">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">There were some errors:</p>
                                <ul class="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Reset Form -->
                <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <!-- Email Field (readonly) -->
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
                                value="{{ $email ?? old('email') }}"
                                required
                                autofocus
                                readonly
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                            >
                        </div>
                    </div>

                    <!-- New Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            New Password
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
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('password') border-red-500 @enderror"
                                placeholder="Enter your new password"
                            >
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be at least 8 characters</p>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                                placeholder="Confirm your new password"
                            >
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <x-button
                            type="submit"
                            size="lg"
                            icon="fas fa-check-circle"
                            class="w-full shadow-sm"
                        >
                            Reset Password
                        </x-button>
                    </div>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="px-8 py-4 surface-panel-alt border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-center items-center text-sm">
                    <a href="{{ route('login') }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-500 font-medium transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back to login
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
