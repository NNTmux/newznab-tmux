<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $meta_title ?? config('app.name') }}@if(isset($meta_title) && $meta_title != '' && $site->metatitle != '') - @endif{{ $site->metatitle ?? '' }}</title>

    <meta name="keywords" content="{{ $meta_keywords ?? '' }}">
    <meta name="description" content="{{ $meta_description ?? '' }}">

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
<body class="bg-gray-50 dark:bg-gray-900 dark:bg-gray-900 font-sans antialiased transition-colors duration-200">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white transition-all duration-300">
                <div class="flex items-center justify-between p-4 border-b border-gray-800 dark:border-gray-700">
                    <a href="{{ $site->home_link ?? url('/') }}" class="flex items-center space-x-2">
                        <i class="fas fa-file-download text-2xl text-blue-500 dark:text-blue-400" aria-hidden="true"></i>
                        <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                    </a>
                </div>

                <nav class="flex-1 overflow-y-auto py-4">
                    @include('partials.sidebar')
                </nav>
            </aside>
        @endauth

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Top Navigation -->
            @auth
                <header class="bg-gray-800 dark:bg-gray-950 text-white shadow-lg">
                    @include('partials.header-menu')
                </header>
            @endauth

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="container mx-auto px-4 py-6">
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

            <!-- Footer -->
            <footer class="mt-auto">
                @include('partials.footer')
            </footer>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle -->
    <button id="mobile-sidebar-toggle" class="md:hidden fixed bottom-4 right-4 z-50 bg-blue-600 dark:bg-blue-700 dark:bg-blue-700 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 dark:hover:bg-blue-800 dark:hover:bg-blue-800">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Dark Mode Toggle -->
    <button id="theme-toggle" class="fixed bottom-4 left-4 z-50 bg-gray-200 dark:bg-gray-700 dark:bg-gray-700 text-gray-800 dark:text-gray-200 dark:text-gray-200 p-3 rounded-full shadow-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200" title="Toggle dark mode">
        <i class="fas fa-moon dark-mode-icon hidden dark:inline"></i>
        <i class="fas fa-sun light-mode-icon inline dark:hidden"></i>
    </button>

    <!-- Toast Notification Container -->
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 350px; pointer-events: none;">
        <!-- Toast notifications will be dynamically inserted here -->
    </div>

    <!-- Scripts -->
    <!-- Toast Notifications (must load before other scripts) -->
    @include('partials.toast-notifications')

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

        // Mobile sidebar toggle
        document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('sidebar')?.classList.toggle('hidden');
        });
    </script>
</body>
</html>

