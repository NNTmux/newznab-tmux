@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo and Title -->
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-blue-600 dark:bg-gray-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                Two-Factor Authentication
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Enter the code from your authenticator app
            </p>
        </div>

        <!-- 2FA Verification Card -->
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

                <div class="mb-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-blue-800 dark:text-blue-200">
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
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center text-2xl tracking-widest font-mono bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
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
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 checked:bg-blue-600 dark:checked:bg-blue-600"
                        >
                        <label for="trust_device" class="ml-2 block text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            Trust this device for 30 days
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                        >
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Verify and Continue
                        </button>
                    </div>
                </form>

                <!-- Back to Login Link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Back to login
                    </a>
                </div>
            </div>

            <!-- Help Section -->
            <div class="bg-gray-50 dark:bg-gray-900 px-8 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-600 dark:text-gray-400">
                    <p class="mb-2">
                        <i class="fas fa-question-circle mr-1"></i>
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

