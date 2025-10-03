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
    <link href="{{ asset('assets/css/all-css.css') }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 text-white transition-all duration-300">
                <div class="flex items-center justify-between p-4 border-b border-gray-800">
                    <a href="{{ $site->home_link ?? url('/') }}" class="flex items-center space-x-2">
                        <i class="fas fa-file-download text-2xl text-blue-500" aria-hidden="true"></i>
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
                <header class="bg-gray-800 text-white shadow-lg">
                    @include('partials.header-menu')
                </header>
            @endauth

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="container mx-auto px-4 py-6">
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            <!-- Footer -->
            <footer class="mt-auto">
                @include('partials.footer')
            </footer>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle -->
    <button id="mobile-sidebar-toggle" class="md:hidden fixed bottom-4 right-4 z-50 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Toast Notification Container -->
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 350px; pointer-events: none;">
        <!-- Toast notifications will be dynamically inserted here -->
    </div>

    <!-- Scripts -->
    <script src="{{ asset('assets/js/all-js.js') }}"></script>

    <!-- Toast Notifications (must load before other scripts) -->
    @include('partials.toast-notifications')

    @stack('scripts')

    <script>
        // Mobile sidebar toggle
        document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('sidebar')?.classList.toggle('hidden');
        });
    </script>
</body>
</html>

