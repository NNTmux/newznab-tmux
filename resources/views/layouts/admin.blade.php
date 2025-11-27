<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta_title ?? 'Admin' }} - {{ config('app.name') }}</title>

    <meta name="description" content="{{ $meta_description ?? 'Admin panel' }}">

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <!-- Dark Mode - Set via meta tag for CSP compliance -->
    <meta name="theme-preference" content="{{ auth()->check() ? (auth()->user()->theme_preference ?? 'light') : 'light' }}">
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans antialiased transition-colors duration-200">
    <div class="min-h-screen flex">
        <!-- Admin Sidebar -->
        <aside class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white">
            <div class="flex items-center justify-between p-4 border-b border-gray-800 dark:border-gray-700">
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
        <div class="flex-1 flex flex-col">
            <!-- Top Bar -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ $page_title ?? 'Admin Dashboard' }}</h1>
                    <div class="flex items-center space-x-4">
                        <a href="{{ url('/') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                            <i class="fas fa-home mr-1"></i> Back to Site
                        </a>
                        <a href="{{ route('logout') }}"
                           data-logout
                           class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 dark:bg-green-900 border-l-4 border-green-500 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-600 text-red-800 dark:text-red-200 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Theme Toggle -->
    <button id="theme-toggle" class="fixed bottom-4 left-4 z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 flex items-center gap-2 touch-target"
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

    <!-- Toast Notification Container -->
    <div id="toast-container">
        <!-- Toast notifications will be dynamically inserted here -->
    </div>

    <!-- Confirmation Modal -->
    @include('partials.confirmation-modal')

    <!-- Scripts -->
    <!-- Toast Notifications (must load before other scripts) -->
    @include('partials.toast-notifications')

    @stack('scripts')

    <script nonce="{{ csp_nonce() }}">
        // Display flash messages as toast notifications
        @if(session('success'))
            window.showToast('{{ session('success') }}', 'success');
        @endif

        @if(session('error'))
            @if(is_array(session('error')))
                @foreach(session('error') as $error)
                    window.showToast('{{ $error }}', 'error');
                @endforeach
            @else
                window.showToast('{{ session('error') }}', 'error');
            @endif
        @endif

        @if(session('warning'))
            window.showToast('{{ session('warning') }}', 'warning');
        @endif

        @if(session('info'))
            window.showToast('{{ session('info') }}', 'info');
        @endif
    </script>

    <!-- Meta tags for theme management (CSP-safe) -->
    @auth
        <meta name="user-authenticated" content="true">
        <meta name="update-theme-url" content="{{ route('profile.update-theme') }}">
    @endauth
</body>
</html>

