@extends('layouts.guest')

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
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 text-sm text-green-700 dark:text-green-200">
                    A fresh verification link has been sent to your email address.
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
                @csrf
                <x-button
                    type="submit"
                    class="w-full"
                >
                    Resend verification email
                </x-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button
                    type="submit"
                    variant="secondary"
                    class="w-full"
                >
                    Sign out
                </x-button>
            </form>
        </div>
    </div>
@endsection

