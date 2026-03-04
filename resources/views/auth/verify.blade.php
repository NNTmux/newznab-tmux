@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-blue-600 dark:bg-gray-700 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-envelope-open-text text-3xl text-white"></i>
                </div>
            </a>
            <h2 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                {{ __('Verify Your Email Address') }}
            </h2>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                @if (session('resent'))
                    <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 mr-3"></i>
                            <span class="text-green-800 dark:text-green-200">
                                {{ __('A fresh verification link has been sent to your email address.') }}
                            </span>
                        </div>
                    </div>
                @endif

                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    {{ __('Before proceeding, please check your email for a verification link.') }}
                </p>
                <p class="text-gray-700 dark:text-gray-300">
                    {{ __('If you did not receive the email') }},
                    <form class="inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium transition">
                            {{ __('click here to request another') }}
                        </button>.
                    </form>
                </p>
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
