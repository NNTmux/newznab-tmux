@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-lg w-full space-y-8">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-blue-600 dark:bg-gray-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                Two Factor Authentication
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Strengthens access security by requiring two methods to verify your identity.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        Two factor authentication protects against phishing, social engineering and password brute force attacks by requiring a second verification step.
                    </p>
                </div>

                @if (session('error'))
                    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mr-3"></i>
                            <span class="text-red-800 dark:text-red-200">{{ session('error') }}</span>
                        </div>
                    </div>
                @endif
                @if (session('success'))
                    <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3"></i>
                            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if(!($data['user']->passwordSecurity))
                    <div class="space-y-4">
                        <p class="text-gray-700 dark:text-gray-300 font-medium">To enable 2FA on your account:</p>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li>Click the button below to generate a unique secret QR code</li>
                            <li>Verify the OTP from your Google Authenticator app</li>
                        </ol>
                        <form method="POST" action="{{ route('generate2faSecret') }}">
                            @csrf
                            <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                                <i class="fas fa-key mr-2"></i>
                                Generate Secret Key to Enable 2FA
                            </button>
                        </form>
                    </div>
                @elseif(!$data['user']->passwordSecurity->google2fa_enable)
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-700 dark:text-gray-300 font-medium mb-3">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 text-xs font-bold mr-2">1</span>
                                Scan this barcode with your Google Authenticator App:
                            </p>
                            <div class="flex justify-center p-4 bg-white rounded-lg border border-gray-200 dark:border-gray-600">
                                {!! $data['google2fa_url'] !!}
                            </div>
                        </div>

                        <div>
                            <p class="text-gray-700 dark:text-gray-300 font-medium mb-3">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 text-xs font-bold mr-2">2</span>
                                Enter the pin code to enable 2FA:
                            </p>
                            <form method="POST" action="{{ route('enable2fa') }}" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="verify-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Authenticator Code
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-key text-gray-400"></i>
                                        </div>
                                        <input id="verify-code" type="password" name="verify-code" required
                                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('verify-code') border-red-500 @enderror"
                                               placeholder="Enter 6-digit code">
                                    </div>
                                    @error('verify-code')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                                    <i class="fas fa-check mr-2"></i>
                                    Enable 2FA
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif($data['user']->passwordSecurity->google2fa_enable)
                    <div class="space-y-6">
                        <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3 text-xl"></i>
                                <span class="text-green-800 dark:text-green-200 font-medium">2FA is currently enabled for your account.</span>
                            </div>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 text-sm">
                            To disable Two Factor Authentication, confirm your password and click the button below.
                        </p>

                        <form method="POST" action="{{ route('disable2fa') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Current Password
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input id="current-password" type="password" name="current-password" required
                                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('current-password') border-red-500 @enderror"
                                           placeholder="Enter your current password">
                                </div>
                                @error('current-password')
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 dark:bg-red-700 hover:bg-red-700 dark:hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                                <i class="fas fa-times mr-2"></i>
                                Disable 2FA
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="px-8 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ url('/') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                    <i class="fas fa-home mr-1"></i> Back to home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
