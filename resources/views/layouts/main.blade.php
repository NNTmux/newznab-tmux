<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply dark mode BEFORE any CSS loads to prevent white flash --}}
    @include('partials.theme-init')

    <title>{{ $meta_title ?? config('app.name') }}@if(isset($meta_title) && $meta_title !== '' && (($site['metatitle'] ?? '') !== '')) - @endif{{ $site['metatitle'] ?? '' }}</title>

    <meta name="keywords" content="{{ $meta_keywords ?? '' }}">
    <meta name="description" content="{{ $meta_description ?? '' }}">

    <!-- Theme Preference - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light' }}">
    <meta name="color-scheme-preference" content="{{ auth()->check() ? (auth()->user()->color_scheme ?? 'blue') : 'blue' }}">
    <!-- CSP Nonce for dynamic script loading -->
    <meta name="csp-nonce" content="{{ csp_nonce() }}">
    @auth
        <meta name="user-authenticated" content="true">
        <meta name="update-theme-url" content="{{ route('profile.update-theme') }}">
    @endauth

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')
</head>
<body class="app-shell bg-gray-50 dark:bg-gray-900 font-sans antialiased">
    <div class="h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white shrink-0 h-full overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b border-white/10 dark:border-white/5">
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
                <header class="surface-header bg-gray-800 dark:bg-gray-950 text-white shrink-0 z-10">
                    @include('partials.header-menu')
                </header>
            @endauth

            <!-- Page Content - This is the scrollable area -->
            <main class="flex-1 overflow-y-auto rounded-xl shadow-inner ring-1 ring-black/10 dark:ring-white/5" data-scroll-container>
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
            <footer class="shrink-0">
                @include('partials.footer')
            </footer>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle -->
    <button id="mobile-sidebar-toggle" class="md:hidden fixed z-50 bg-primary-600 dark:bg-primary-700 text-white p-4 rounded-full shadow-lg hover:bg-primary-700 dark:hover:bg-primary-800 transition-all touch-target bottom-[max(5rem,calc(env(safe-area-inset-bottom)+4rem))] right-[max(1rem,env(safe-area-inset-right))]" aria-label="Toggle Sidebar">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <!-- Back to Top -->
    @include('partials.back-to-top')

    <!-- Theme Toggle -->
    @php $themePreference = auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light'; @endphp
    @guest
        <button id="theme-toggle" class="fixed z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 flex items-center gap-2 touch-target bottom-[max(1rem,env(safe-area-inset-bottom))] right-[max(1rem,env(safe-area-inset-right))]"
                title="{{ ucfirst($themePreference) }} Mode">
            <i id="theme-icon" class="fas {{ $themePreference === 'dark' ? 'fa-moon' : ($themePreference === 'system' ? 'fa-desktop' : 'fa-sun') }}"></i>
            <span id="theme-label" class="text-xs font-medium hidden sm:inline">{{ ucfirst($themePreference) }}</span>
        </button>
    @endguest

    <!-- Confirmation Modal (used on many pages) -->
    @include('partials.confirmation-modal')

    <!-- Toast Notifications (Alpine.js CSP Safe) -->
    @include('partials.toast-notifications')

    {{-- Release-specific modals: pushed by pages that show releases --}}
    @stack('modals')

    @stack('scripts')

    <!-- Theme Management Data (moved to csp-safe.js) -->
    @php $colorScheme = auth()->check() ? (auth()->user()->color_scheme ?? 'blue') : 'blue'; @endphp
    <div id="current-theme-data"
         data-theme="{{ $themePreference }}"
         data-color-scheme="{{ $colorScheme }}"
         data-authenticated="{{ auth()->check() ? 'true' : 'false' }}"
         data-update-url="{{ route('profile.update-theme') }}"
         class="hidden">
    </div>

    <!-- Flash Messages Data (moved to csp-safe.js) -->
    <div id="flash-messages-data"
         data-messages="{{ json_encode([
             'success' => session('success'),
             'error' => session('error'),
             'warning' => session('warning'),
             'info' => session('info')
         ]) }}"
         class="hidden">
    </div>
</body>
</html>

