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
    @include('partials.favicons')

    @include('partials.theme-meta')
    <!-- CSP Nonce for dynamic script loading -->
    <meta name="csp-nonce" content="{{ csp_nonce() }}">

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
                    <div class="flex items-center">
                        <button type="button" id="mobile-sidebar-toggle" class="touch-target ml-2 inline-flex items-center justify-center rounded-xl p-2 text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 md:hidden" aria-label="Open account navigation" aria-controls="sidebar" aria-expanded="false">
                            <i class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
                        </button>
                        <div class="min-w-0 flex-1">
                            @include('partials.header-menu')
                        </div>
                    </div>
                </header>
            @else
                <header class="layout-topbar surface-header z-30 shrink-0 text-white">
                    @include('partials.guest-topbar')
                </header>
            @endauth

            <!-- Page Content - This is the scrollable area -->
            <main id="main-content" class="layout-main flex-1 overflow-y-auto" data-scroll-container tabindex="-1">
                <div class="app-view-stack layout-page-container container mx-auto max-w-[1600px] px-3 py-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-5 sm:py-6 lg:px-8">
                    {{-- Flash messages are shown as toasts (partials.toast-notifications). --}}
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

    <!-- Back to Top -->
    @include('partials.back-to-top')

    @php $themePreference = $userTheme; @endphp

    <!-- Confirmation Modal (used on many pages) -->
    @include('partials.confirmation-modal')

    <!-- Toast Notifications (Alpine.js CSP Safe) -->
    @include('partials.toast-notifications')

    {{-- Release-specific modals: pushed by pages that show releases --}}
    @stack('modals')

    @stack('scripts')

    <!-- Theme data read by the Alpine theme store -->
    @php $colorScheme = $userColorScheme; @endphp
    <div id="current-theme-data"
         data-theme="{{ $themePreference }}"
         data-color-scheme="{{ $colorScheme }}"
         data-authenticated="{{ $loggedin ? 'true' : 'false' }}"
         data-update-url="{{ route('profile.update-theme') }}"
         class="hidden">
    </div>

    <!-- Flash messages read by the Alpine toast store -->
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
