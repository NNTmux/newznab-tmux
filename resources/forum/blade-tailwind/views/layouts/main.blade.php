<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-authenticated" content="true">
        <meta name="theme-preference" content="{{ auth()->user()->theme_preference ?? 'light' }}">
    @else
        <meta name="user-authenticated" content="false">
    @endauth
    <meta name="default-category-color" content="{{ config('forum.frontend.default_category_color') }}">

    <title>
        @if (isset($thread_title))
            {{ $thread_title }} —
        @endif
        @if (isset($category))
            {{ $category->title }} —
        @endif
        {{ trans('forum::general.home_title') }}
    </title>

    @vite(['resources/forum/blade-tailwind/css/forum.css', 'resources/forum/blade-tailwind/js/forum.js'])
</head>
<body class="forum bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
    <nav class="v-navbar bg-white dark:bg-gray-800 shadow py-4 transition-colors duration-200">
        <div class="container mx-auto px-4 md:flex md:items-center md:gap-4">
            <div class="flex justify-between items-center">
                <a class="text-lg font-semibold text-gray-900 dark:text-white" href="{{ url(config('forum.frontend.router.prefix')) }}">Laravel Forum</a>
                <button class="navbar-toggler block md:hidden border rounded-md px-2 py-1 text-gray-700 dark:text-gray-300 dark:border-gray-600" type="button" :class="{ collapsed: isCollapsed }" @click="isCollapsed = !isCollapsed">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="navbar-toggler-icon w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
            <div class="grow justify-between navbar-collapse" :class="{ 'flex flex-col': !isCollapsed, 'hidden md:flex': isCollapsed }">
                <ul class="flex flex-col md:flex-row gap-3 mb-4 md:mb-0">
                    <li>
                        <a class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ route('home') }}">Home</a>
                    </li>
                    <li>
                        <a class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ url(config('forum.frontend.router.prefix')) }}">{{ trans('forum::general.index') }}</a>
                    </li>
                    <li>
                        <a class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ route('forum.recent') }}">{{ trans('forum::threads.recent') }}</a>
                    </li>
                    @auth
                        <li>
                            <a class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ route('forum.unread') }}">{{ trans('forum::threads.unread_updated') }}</a>
                        </li>
                    @endauth
                    @can ('moveCategories')
                        <li>
                            <a class="text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ route('forum.category.manage') }}">{{ trans('forum::general.manage') }}</a>
                        </li>
                    @endcan
                </ul>
                <ul class="navbar-nav flex gap-4 flex-col md:flex-row">
                    @if (Auth::check())
                        <li class="nav-item dropdown relative">
                            <a class="dropdown-toggle text-gray-500 dark:text-gray-400 flex items-center gap-1 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="#" id="navbarDropdownMenuLink" @click="isUserDropdownCollapsed = !isUserDropdownCollapsed">
                                {{ $username }}

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </a>
                            <div class="border dark:border-gray-600 absolute left-0 bg-white dark:bg-gray-800 rounded-md w-44 divide-y dark:divide-gray-600 shadow-lg" :class="{ hidden: isUserDropdownCollapsed }" aria-labelledby="navbarDropdownMenuLink">
                                <a class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="{{ url('/logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    Log out
                                </a>
                                <form id="logout-form" action="{{ url('/logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                            </div>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ url('/login') }}">Log in</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors" href="{{ url('/register') }}">Register</a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <div id="main" class="container mx-auto p-4">
        @include ('forum::partials.breadcrumbs')
        @include ('forum::partials.alerts')

        @yield('content')
    </div>

    @yield('footer')
</body>
</html>
