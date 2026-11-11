@extends('layouts.guest')

@section('title', __('Verify your email address'))

@section('content')
<div class="min-h-screen auth-page flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-accent dark:bg-accent-hover rounded-full flex items-center justify-center shadow-lg">
                    <i aria-hidden="true" class="fas fa-envelope-open-text text-3xl text-white"></i>
                </div>
            </a>
            <h1 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white">
                {{ __('Verify Your Email Address') }}
            </h1>
        </div>

        <div class="auth-card rounded-xl shadow-xl overflow-hidden">
            <div class="px-8 py-6">
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    {{ __('Before proceeding, please check your email for a verification link.') }}
                </p>
                <p class="text-gray-700 dark:text-gray-300">
                    {{ __('If you did not receive the email') }},
                    <form class="inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="text-link dark:text-primary-400 hover:text-primary-500 font-medium transition">
                            {{ __('click here to request another') }}
                        </button>.
                    </form>
                </p>
            </div>

            <div class="px-8 py-4 surface-panel-alt border-t border-gray-200 dark:border-gray-700">
                <a href="{{ url('/') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition">
                    <i aria-hidden="true" class="fas fa-home mr-1"></i> Back to home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
