<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-shell">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply dark mode BEFORE any CSS loads to prevent white flash --}}
    @include('partials.theme-init')

    <title>{{ $meta_title ?? 'Admin' }} - {{ config('app.name') }}</title>

    <meta name="description" content="{{ $meta_description ?? 'Admin panel' }}">

    <!-- Dark Mode - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ $userTheme }}">

    <!-- TinyMCE API Key -->
    <meta name="tinymce-api-key" content="{{ config('tinymce.api_key', 'no-api-key') }}">

    <!-- CSP Nonce for dynamic script loading -->
    <meta name="csp-nonce" content="{{ csp_nonce() }}">

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
    @stack('styles')
</head>
<body class="app-shell font-sans antialiased">
    <a href="#main-content" class="skip-link">Skip to admin content</a>

    <div class="layout-shell layout-shell--admin h-screen flex">
        <!-- Admin Sidebar -->
        <aside id="sidebar" class="layout-sidebar layout-sidebar--admin fixed inset-y-0 left-0 z-50 hidden h-full w-72 shrink-0 flex-col overflow-y-auto text-white md:static md:z-auto md:flex md:w-64" aria-label="Admin navigation">
            <div class="layout-sidebar__brand flex items-center justify-between gap-3 p-4">
                <a href="{{ route('admin.index') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400">
                    <span class="layout-sidebar__logo flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                        <x-site-logo :url="$siteLogoUrl ?? null" fallback-icon="fas fa-sliders" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-semibold">Admin Panel</span>
                        <span class="block text-xs font-medium text-white/55">Operations workspace</span>
                    </span>
                </a>
                <button type="button" id="mobile-sidebar-close" class="touch-target inline-flex items-center justify-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white md:hidden" aria-label="Close admin navigation">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="layout-sidebar__nav flex-1 overflow-y-auto py-3" aria-label="Admin">
                @include('partials.admin-menu')
            </nav>
        </aside>
        <button type="button" id="mobile-sidebar-backdrop" class="layout-sidebar-backdrop fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm md:hidden" aria-label="Close admin navigation" tabindex="-1"></button>

        <!-- Main Content -->
        <div class="layout-content flex h-full min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="layout-topbar surface-header z-30 shrink-0 text-white">
                <div class="layout-topbar__inner flex min-h-16 items-center justify-between gap-3 px-3 py-2 sm:px-5 lg:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button id="mobile-sidebar-toggle" type="button" class="touch-target inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 md:hidden" aria-label="Open admin navigation" aria-controls="sidebar" aria-expanded="false">
                            <i class="fas fa-bars" aria-hidden="true"></i>
                        </button>
                        <div class="min-w-0">
                            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-white/45">Administration</p>
                            <p class="truncate text-sm font-semibold text-white sm:text-base">{{ $page_title ?? 'Dashboard' }}</p>
                        </div>
                    </div>
                    <div class="layout-topbar__tools flex items-center gap-1 rounded-2xl border border-white/10 bg-white/5 p-1 shadow-sm backdrop-blur-sm sm:gap-2">
                        <button id="theme-toggle" class="touch-target flex items-center gap-2 rounded-xl px-3 py-2 text-gray-100 transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400"
                                title="{{ ucfirst($userTheme) }} Mode"
                                aria-label="Change theme. Current theme: {{ $userTheme }}">
                            <i id="theme-icon" class="fas
                                @if($userTheme === 'dark')
                                    fa-moon
                                @elseif($userTheme === 'system')
                                    fa-desktop
                                @else
                                    fa-sun
                                @endif
                            "></i>
                            <span id="theme-label" class="text-xs font-medium hidden sm:inline">
                                {{ ucfirst($userTheme) }}
                            </span>
                        </button>
                        <a href="{{ url($site->homeLink ?? '/') }}" class="touch-target inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-white/75 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400" title="Back to site" aria-label="View site">
                            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="hidden lg:inline">View site</span>
                        </a>
                        <a href="{{ route('logout') }}"
                           data-logout
                           class="touch-target inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-red-300 transition hover:bg-red-400/10 hover:text-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300" title="Log out" aria-label="Log out">
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="hidden xl:inline">Log out</span>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content - Scrollable Area -->
            <main id="main-content" class="app-view-stack app-view-stack--admin layout-main flex-1 overflow-y-auto p-3 sm:p-5 lg:p-6" data-scroll-container tabindex="-1">
                @unless(trim($__env->yieldContent('suppress_layout_flash')))
                    @if(session('success'))
                        <div class="layout-flash layout-flash--success mb-5" role="status">
                            <i class="fas fa-circle-check" aria-hidden="true"></i><div>{{ session('success') }}</div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="layout-flash layout-flash--error mb-5" role="alert">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i><div>{{ session('error') }}</div>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="layout-flash layout-flash--warning mb-5" role="status">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i><div>{{ session('warning') }}</div>
                        </div>
                    @endif
                @endunless

                @yield('content')
            </main>

            <!-- Admin Footer - Fixed at bottom -->
            <footer class="layout-footer shrink-0">
                <div class="px-6 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-300 dark:text-gray-400">
                        <p>&copy; {{ now()->year }} <a href="https://github.com/NNTmux/newznab-tmux" class="text-primary-400 underline underline-offset-2 hover:text-primary-300 transition">NNTmux</a> Admin Panel</p>
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
