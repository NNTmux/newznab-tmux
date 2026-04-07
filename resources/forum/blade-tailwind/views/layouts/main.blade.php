@php
    $category = $category ?? null;
    $thread = $thread ?? null;
    $breadcrumbs_append = $breadcrumbs_append ?? [];
    $thread_title = $thread_title ?? null;

    $meta_title = trim(collect([
        $thread_title,
        data_get($category, 'title'),
        trans('forum::general.home_title'),
    ])->filter()->join(' — '));
@endphp

@extends('layouts.main')

@push('meta')
    <meta name="default-category-color" content="{{ config('forum.frontend.default_category_color') }}">
@endpush

@push('styles')
    @vite('resources/forum/blade-tailwind/css/forum.css')
@endpush

@prepend('scripts')
    @vite('resources/forum/blade-tailwind/js/forum.js')
@endprepend

@push('scripts')
    @stack('forum-page-scripts')
@endpush

@section('content')
    <div class="forum space-y-6">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gradient-to-r from-slate-50 via-white to-blue-50 px-5 py-5 dark:border-gray-700 dark:from-gray-900 dark:via-gray-800 dark:to-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-400">Community</p>
                        <h1 class="!my-0 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }} Forum</h1>
                        <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                            Discuss releases, ask for help, and follow staff updates without leaving the main {{ config('app.name') }} interface.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        @guest
                            <a href="{{ url('/login') }}" class="inline-flex items-center rounded-full border border-gray-300 px-4 py-2 font-medium text-gray-700 transition hover:border-blue-300 hover:text-blue-600 dark:border-gray-600 dark:text-gray-300 dark:hover:border-blue-500 dark:hover:text-blue-400">Log in</a>
                            <a href="{{ url('/register') }}" class="inline-flex items-center rounded-full bg-blue-600 px-4 py-2 font-medium text-white transition hover:bg-blue-500">Register</a>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-4 py-2 font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $username }}</span>
                            <a href="{{ url('/logout') }}" class="inline-flex items-center rounded-full border border-gray-300 px-4 py-2 font-medium text-gray-700 transition hover:border-red-300 hover:text-red-600 dark:border-gray-600 dark:text-gray-300 dark:hover:border-red-500 dark:hover:text-red-400" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Log out</a>
                            <form id="logout-form" action="{{ url('/logout') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        @endguest
                    </div>
                </div>

                <div class="v-navbar mt-5 flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('home') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-blue-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-blue-400' }}">Home</a>
                        <a href="{{ url(config('forum.frontend.router.prefix')) }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.index') || request()->routeIs('forum.category.*') ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-blue-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-blue-400' }}">{{ trans('forum::general.index') }}</a>
                        <a href="{{ route('forum.recent') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.recent') ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-blue-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-blue-400' }}">{{ trans('forum::threads.recent') }}</a>
                        @auth
                            <a href="{{ route('forum.unread') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.unread') ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-blue-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-blue-400' }}">{{ trans('forum::threads.unread_updated') }}</a>
                        @endauth
                        @can ('moveCategories')
                            <a href="{{ route('forum.category.manage') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.category.manage') ? 'bg-amber-500 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-amber-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-amber-400' }}">{{ trans('forum::categories.manage') }}</a>
                        @endcan
                        @can ('approveThreads')
                            <a href="{{ route('forum.pending-approval.threads') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.pending-approval.threads') ? 'bg-orange-500 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-orange-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-orange-400' }}">{{ trans('forum::threads.pending_approval') }}</a>
                        @endcan
                        @can ('approvePosts')
                            <a href="{{ route('forum.pending-approval.posts') }}" class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition {{ request()->routeIs('forum.pending-approval.posts') ? 'bg-orange-500 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:text-orange-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:text-orange-400' }}">{{ trans('forum::posts.pending_approval') }}</a>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="space-y-4 px-5 py-4 sm:px-6">
                @include ('forum::partials.breadcrumbs')
                @include ('forum::partials.alerts')
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
            @yield('forum-content')
        </section>
    </div>
@endsection
