@extends('layouts.guest')

@section('content')
<div class="min-h-screen auth-page flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-primary-600 dark:bg-primary-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                Two Factor Authentication
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Enter the pin from your Google Authenticator app
            </p>
        </div>

        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <form action="{{ route('2faVerify') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="one_time_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            One Time Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-400"></i>
                            </div>
                            <input
                                id="one_time_password"
                                name="one_time_password"
                                type="text"
                                required
                                autofocus
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition @error('one_time_password') border-red-500 @enderror"
                                placeholder="Enter 6-digit code"
                            >
                        </div>
                        @error('one_time_password')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-button
                        type="submit"
                        class="w-full"
                        icon="fas fa-sign-in-alt"
                    >
                        Authenticate
                    </x-button>
                </form>
            </div>

            <div class="px-8 py-4 surface-panel-alt border-t border-gray-200 dark:border-gray-700">
                <a href="{{ url('/') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                    <i class="fas fa-home mr-1"></i> Back to home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
