@extends('layouts.guest')

@section('content')
<div class="auth-page min-h-dvh px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto flex min-h-[calc(100dvh-5rem)] max-w-3xl items-center justify-center">
        <div class="auth-card w-full rounded-xl p-8 text-center shadow-xl">
            <a href="{{ url('/') }}" class="workspace-brand-mark inline-flex h-16 w-16 items-center justify-center rounded-2xl" aria-label="{{ config('app.name') }} home">
                <i class="fas fa-layer-group text-2xl" aria-hidden="true"></i>
            </a>

            <h1 class="mt-6 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ config('app.name') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-gray-600 dark:text-gray-400">Your Usenet index is ready. Sign in to search releases, manage saved media, and use your download basket.</p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                @auth
                    <x-button-link :href="route('search')" icon="fas fa-search">Search Releases</x-button-link>
                    <x-button-link :href="route('All')" variant="secondary" icon="fas fa-list-ul">Browse All</x-button-link>
                @else
                    <x-button-link :href="route('login')" icon="fas fa-sign-in-alt">Sign In</x-button-link>
                    @if(Route::has('register'))
                        <x-button-link :href="route('register')" variant="secondary" icon="fas fa-user-plus">Create Account</x-button-link>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
