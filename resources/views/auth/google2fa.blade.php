@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
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
                Enter the pin from your Google Authenticator app
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
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
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('one_time_password') border-red-500 @enderror"
                                placeholder="Enter 6-digit code"
                            >
                        </div>
                        @error('one_time_password')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Authenticate
                    </button>
                </form>
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
