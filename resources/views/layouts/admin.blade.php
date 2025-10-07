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

    <!-- Dark Mode Script (must be inline to prevent flash) -->
    <script>
        // Initialize theme before page renders to prevent flash
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
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
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
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

    <!-- Dark Mode Toggle -->
    <button id="theme-toggle" class="fixed bottom-4 left-4 z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 p-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200" title="Toggle dark mode">
        <i class="fas fa-moon dark-mode-icon hidden dark:inline"></i>
        <i class="fas fa-sun light-mode-icon inline dark:hidden"></i>
    </button>

    <!-- Scripts -->
    @stack('scripts')

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');

        themeToggle?.addEventListener('click', function() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');

            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        });
    </script>
</body>
</html>

