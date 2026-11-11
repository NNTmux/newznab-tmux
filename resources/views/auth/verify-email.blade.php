@extends('layouts.guest')

@section('title', __('Verify your email address'))

@section('content')
    <div class="min-h-screen auth-page flex items-center justify-center px-4">
        <div class="w-full max-w-md auth-card shadow-md rounded-xl p-8 space-y-6">
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Verify your email address</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Before continuing, please check your inbox for the verification link we emailed to you.
                </p>
            </div>

            @if (session('status') === 'resent')
                <div class="rounded-md bg-success-50 dark:bg-success-900/30 p-4 text-sm text-success-700 dark:text-success-200">
                    A fresh verification link has been sent to your email address.
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
                @csrf
                <button
                    type="submit"
                    class="w-full inline-flex justify-center rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    Sign out
                </button>
            </form>
        </div>
    </div>
@endsection

