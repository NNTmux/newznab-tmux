<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply dark mode BEFORE any CSS loads to prevent white flash --}}
    @include('partials.theme-init')

    <title>{{ $meta_title ?? 'Admin' }} - {{ config('app.name') }}</title>

    <meta name="description" content="{{ $meta_description ?? 'Admin panel' }}">

    <!-- Dark Mode - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light' }}">

    <!-- TinyMCE API Key -->
    <meta name="tinymce-api-key" content="{{ config('tinymce.api_key', 'no-api-key') }}">

    <!-- CSP Nonce for dynamic script loading -->
    <meta name="csp-nonce" content="{{ csp_nonce() }}">

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')
</head>
<body class="app-shell bg-gray-100 dark:bg-gray-900 font-sans antialiased">
    <div class="h-screen flex">
        <!-- Admin Sidebar -->
        <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white shrink-0 h-full overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b border-white/10 dark:border-white/5">
                <a href="{{ route('admin.index') }}" class="flex items-center space-x-2">
                    <i class="fas fa-cog text-2xl text-blue-500 dark:text-blue-400"></i>
                    <span class="text-xl font-semibold">Admin Panel</span>
                </a>
            </div>

            <nav class="flex-1 overflow-y-auto py-4">
                @include('partials.admin-menu')
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Top Bar -->
            <header class="surface-header bg-gray-800 dark:bg-gray-950 text-white shrink-0 z-10">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-lg font-semibold text-gray-200">{{ $page_title ?? 'Admin Dashboard' }}</h1>
                    <div class="flex items-center space-x-4">
                        <button id="theme-toggle" class="bg-gray-700 dark:bg-gray-800 text-gray-100 dark:text-gray-200 px-3 py-2 rounded-lg shadow hover:bg-gray-600 dark:hover:bg-gray-700 transition-all duration-200 flex items-center gap-2 touch-target"
                                title="{{ ucfirst(auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light') }} Mode">
                            <i id="theme-icon" class="fas
                                @if(auth()->check())
                                    @if((auth()->user()->theme_preference ?? 'light') === 'dark')
                                        fa-moon
                                    @elseif((auth()->user()->theme_preference ?? 'light') === 'system')
                                        fa-desktop
                                    @else
                                        fa-sun
                                    @endif
                                @else
                                    fa-sun
                                @endif
                            "></i>
                            <span id="theme-label" class="text-xs font-medium hidden sm:inline">
                                @if(auth()->check())
                                    {{ ucfirst(auth()->user()->theme_preference ?? 'light') }}
                                @else
                                    Light
                                @endif
                            </span>
                        </button>
                        <a href="{{ url('/') }}" class="text-gray-300 dark:text-gray-400 hover:text-white transition">
                            <i class="fas fa-home mr-1"></i> Back to Site
                        </a>
                        <a href="{{ route('logout') }}"
                           data-logout
                           class="text-red-400 hover:text-red-300 transition">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content - Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-6 rounded-xl shadow-inner ring-1 ring-black/10 dark:ring-white/5" data-scroll-container>
                @unless(trim($__env->yieldContent('suppress_layout_flash')))
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 dark:border-red-600 text-red-800 dark:text-red-200 rounded">
                            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 dark:border-yellow-600 text-yellow-800 dark:text-yellow-200 rounded">
                            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                        </div>
                    @endif
                @endunless

                @yield('content')
            </main>

            <!-- Admin Footer - Fixed at bottom -->
            <footer class="shrink-0">
                <div class="px-6 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-300 dark:text-gray-400">
                        <p>&copy; {{ now()->year }} <a href="https://github.com/NNTmux/newznab-tmux" class="text-primary-400 hover:text-primary-300 transition">NNTmux</a> Admin Panel</p>
                        <p>{{ config('app.name') }} v{{ config('nntmux.versions.git.tag') ?? '1.0.0' }}</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Back to Top -->
    @include('partials.back-to-top')

    <!-- Confirmation Modal -->
    @include('partials.confirmation-modal')

    <!-- Toast Notifications (Alpine.js CSP Safe) -->
    @include('partials.toast-notifications')

    @stack('scripts')

    <!-- Flash Messages Data (read by Alpine toast store on init) -->
    <div id="flash-messages-data"
         data-messages="{{ json_encode([
             'success' => session('success'),
             'error' => session('error'),
             'warning' => session('warning'),
             'info' => session('info')
         ]) }}"
         class="hidden">
    </div>

    <!-- Meta tags for theme management (CSP-safe) -->
    @auth
        <meta name="user-authenticated" content="true">
        <meta name="update-theme-url" content="{{ route('profile.update-theme') }}">
    @endauth
</body>
</html>

