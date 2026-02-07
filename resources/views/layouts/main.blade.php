<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta_title ?? config('app.name') }}@if(isset($meta_title) && $meta_title != '' && $site['metatitle'] != '') - @endif{{ $site['metatitle'] ?? '' }}</title>

    <meta name="keywords" content="{{ $meta_keywords ?? '' }}">
    <meta name="description" content="{{ $meta_description ?? '' }}">

    <!-- Theme Preference - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light' }}">
    @auth
        <meta name="user-authenticated" content="true">
        <meta name="update-theme-url" content="{{ route('profile.update-theme') }}">
    @endauth

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <!-- Dark Mode Script (must use nonce to prevent flash) -->
    <script nonce="{{ csp_nonce() }}">
        // Initialize theme before page renders to prevent flash
        (function() {
            @auth
                // Use user's database preference when authenticated
                const userThemePreference = '{{ auth()->user()->theme_preference ?? 'light' }}';

                if (userThemePreference === 'system') {
                    // Use OS preference
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    }
                } else if (userThemePreference === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            @else
                // Fallback to localStorage for non-authenticated users
                const theme = localStorage.getItem('theme') || 'light';
                if (theme === 'system') {
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    }
                } else if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            @endauth
        })();
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans antialiased transition-colors duration-200">
    <div class="h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white flex-shrink-0 h-full overflow-y-auto rounded-r-xl">
                <div class="flex items-center justify-between p-4 border-b border-gray-800 dark:border-gray-700">
                    <a href="{{ $site['home_link'] ?? url('/') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('assets/images/logo.svg') }}" alt="{{ config('app.name') }} Logo" class="w-12 h-12" aria-hidden="true">
                        <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                    </a>
                </div>

                <nav class="flex-1 overflow-y-auto py-4">
                    @include('partials.sidebar')
                </nav>
            </aside>
        @endauth

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Top Navigation -->
            @auth
                <header class="bg-gray-800 dark:bg-gray-950 text-white shadow-lg flex-shrink-0 z-10 rounded-b-xl">
                    @include('partials.header-menu')
                </header>
            @endauth

            <!-- Page Content - This is the scrollable area -->
            <main class="flex-1 overflow-y-auto">
                <div class="container mx-auto px-4 py-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded-lg">
                            @if(is_array(session('error')))
                                @foreach(session('error') as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            @else
                                {{ session('error') }}
                            @endif
                        </div>
                    @endif

                    @yield('content')
                    @if(isset($content) && is_string($content))
                        {!! $content !!}
                    @endif
                </div>
            </main>

            <!-- Footer - Fixed at bottom -->
            <footer class="flex-shrink-0 rounded-t-xl">
                @include('partials.footer')
            </footer>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle -->
    <button id="mobile-sidebar-toggle" class="md:hidden fixed z-50 bg-blue-600 dark:bg-blue-700 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-all touch-target bottom-[max(5rem,calc(env(safe-area-inset-bottom)+4rem))] right-[max(1rem,env(safe-area-inset-right))]" aria-label="Toggle Sidebar">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <!-- Theme Toggle -->
    <button id="theme-toggle" class="fixed z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 flex items-center gap-2 touch-target bottom-[max(1rem,env(safe-area-inset-bottom))] left-[max(1rem,env(safe-area-inset-left))]"
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

    <!-- Confirmation Modal -->
    @include('partials.confirmation-modal')

    <!-- File List Modal -->
    @include('partials.filelist-modal')

    <!-- NFO Modal -->
    @include('partials.nfo-modal')

    <!-- Preview/Sample Image Modal -->
    @include('partials.preview-modal')

    <!-- Media Info Modal -->
    @include('partials.mediainfo-modal')

    <!-- Image Modal -->
    @include('partials.image-modal')

    <!-- Toast Notifications (Alpine.js CSP Safe) -->
    @include('partials.toast-notifications')

    @stack('scripts')

    <!-- Theme Management Data (moved to csp-safe.js) -->
    <div id="current-theme-data"
         data-theme="{{ auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light' }}"
         data-authenticated="{{ auth()->check() ? 'true' : 'false' }}"
         data-update-url="{{ route('profile.update-theme') }}"
         style="display: none;">
    </div>

    <!-- Flash Messages Data (moved to csp-safe.js) -->
    <div id="flash-messages-data"
         data-messages="{{ json_encode([
             'success' => session('success'),
             'error' => session('error'),
             'warning' => session('warning'),
             'info' => session('info')
         ]) }}"
         style="display: none;">
    </div>
</body>
</html>

