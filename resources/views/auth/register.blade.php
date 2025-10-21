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
                Create your account
            </p>
        </div>

        <!-- Register Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <!-- Registration Status Error Message -->
                @if(!empty($error) && $showregister == 0)
                    <div class="p-6 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-yellow-600 dark:text-yellow-400 mr-3 mt-0.5 text-xl"></i>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Registration Not Available</h3>
                                <p class="text-sm text-yellow-700 dark:text-yellow-300">{{ $error }}</p>
                                @if(strpos($error, 'invite only') !== false)
                                    <p class="mt-3 text-sm text-yellow-700 dark:text-yellow-300">
                                        <i class="fas fa-envelope mr-1"></i>
                                        You need a valid invitation link to register. Please check your email or contact an existing member.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Validation Error Messages -->
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

                <!-- Register Form -->
                @if($showregister == 1)
                <form method="POST" action="{{ route('register') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="action" value="submit">

                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Username
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
                                placeholder="Choose a username"
                            >
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

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
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('email') border-red-500 @enderror"
                                placeholder="your@email.com"
                            >
                        </div>
                        @error('email')
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
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('password') border-red-500 @enderror"
                                placeholder="Create a strong password"
                            >
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
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
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Confirm your password"
                            >
                        </div>
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

                    <!-- Terms and Conditions -->
                    <div class="flex items-start">
                        <input
                            id="terms"
                            name="terms"
                            type="checkbox"
                            required
                            class="h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded mt-1"
                        >
                        <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            I agree to the
                            <a href="{{ url('/terms-and-conditions') }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-500">Terms and Conditions</a>
                            and
                            <a href="{{ url('/privacy-policy') }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:text-blue-500">Privacy Policy</a>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out"
                        >
                            <i class="fas fa-user-plus mr-2"></i>
                            Create Account
                        </button>
                    </div>
                </form>
                @endif
            </div>

            <!-- Card Footer -->
            <div class="px-8 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm">
                    <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium transition">
                        <i class="fas fa-sign-in-alt mr-1"></i> Already have an account? Sign in
                    </a>

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
