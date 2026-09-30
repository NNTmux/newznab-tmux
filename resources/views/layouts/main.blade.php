<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply dark mode BEFORE any CSS loads to prevent white flash --}}
    @include('partials.theme-init')

    <title>{{ $meta_title ?? config('app.name') }}@if(isset($meta_title) && $meta_title !== '' && (($site->metaTitle ?? '') !== '')) - @endif{{ $site->metaTitle ?? '' }}</title>

    <meta name="keywords" content="{{ $meta_keywords ?? '' }}">
    <meta name="description" content="{{ $meta_description ?? '' }}">

    <!-- Theme Preference - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ $userTheme }}">
    <meta name="color-scheme-preference" content="{{ $userColorScheme }}">
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
<body class="app-shell font-sans antialiased">
    <a href="#main-content" class="skip-link">Skip to content</a>

    <div class="layout-shell h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="layout-sidebar fixed inset-y-0 left-0 z-50 hidden h-full w-72 shrink-0 flex-col overflow-y-auto text-white md:static md:z-auto md:flex md:w-64" aria-label="Account navigation">
                <div class="layout-sidebar__brand flex items-center justify-between gap-3 p-4">
                    <a href="{{ url($site->homeLink ?? '/') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400">
                        <span class="layout-sidebar__logo flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                            <x-site-logo :url="$siteLogoUrl ?? null" />
                        </span>
                        <span class="min-w-0 truncate text-lg font-semibold">{{ config('app.name') }}</span>
                    </a>
                    <button type="button" id="mobile-sidebar-close" class="touch-target inline-flex items-center justify-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white md:hidden" aria-label="Close navigation">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <nav class="layout-sidebar__nav flex-1 overflow-y-auto py-3" aria-label="User">
                    @include('partials.sidebar')
                </nav>
            </aside>
            <button type="button" id="mobile-sidebar-backdrop" class="layout-sidebar-backdrop fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm md:hidden" aria-label="Close navigation" tabindex="-1"></button>
        @endauth

        <!-- Main Content -->
        <div class="layout-content flex h-full min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Top Navigation -->
            @auth
                <header class="layout-topbar surface-header z-30 shrink-0 text-white">
                    @include('partials.header-menu')
                </header>
            @endauth

            <!-- Page Content - This is the scrollable area -->
            <main id="main-content" class="layout-main flex-1 overflow-y-auto" data-scroll-container tabindex="-1">
                <div class="app-view-stack layout-page-container container mx-auto max-w-[1600px] px-3 py-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-5 sm:py-6 lg:px-8">
                    @if(session('success'))
                        <div class="layout-flash layout-flash--success mb-5" role="status">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                            <div>{{ session('success') }}</div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="layout-flash layout-flash--error mb-5" role="alert">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                            <div>
                                @if(is_array(session('error')))
                                    @foreach(session('error') as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                @else
                                    {{ session('error') }}
                                @endif
                            </div>
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
    @auth
        <button type="button" id="mobile-sidebar-toggle" class="layout-mobile-sidebar-toggle touch-target fixed z-30 inline-flex items-center gap-2 rounded-full bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 dark:bg-primary-700 dark:hover:bg-primary-600 md:hidden bottom-[max(5rem,calc(env(safe-area-inset-bottom)+4rem))] right-[max(1rem,env(safe-area-inset-right))]" aria-label="Open account navigation" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-compass" aria-hidden="true"></i>
            <span>Navigate</span>
        </button>
    @endauth

    <!-- Back to Top -->
    @include('partials.back-to-top')

    <!-- Theme Toggle -->
    @php $themePreference = $userTheme; @endphp
    @guest
        <button id="theme-toggle" class="fixed z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 flex items-center gap-2 touch-target bottom-[max(1rem,env(safe-area-inset-bottom))] right-[max(1rem,env(safe-area-inset-right))]"
                title="{{ ucfirst($themePreference) }} Mode"
                aria-label="Change theme. Current theme: {{ $themePreference }}">
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
    @php $colorScheme = $userColorScheme; @endphp
    <div id="current-theme-data"
         data-theme="{{ $themePreference }}"
         data-color-scheme="{{ $colorScheme }}"
         data-authenticated="{{ $loggedin ? 'true' : 'false' }}"
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
