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

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <!-- Dark Mode Script (must be inline to prevent flash) -->
    <script>
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
<body class="bg-gray-50 dark:bg-gray-900 dark:bg-gray-900 font-sans antialiased transition-colors duration-200">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        @auth
            <aside id="sidebar" class="hidden md:flex md:flex-col w-64 bg-gray-900 dark:bg-gray-950 text-white transition-all duration-300">
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
    <button id="mobile-sidebar-toggle" class="md:hidden fixed bottom-20 right-4 z-50 bg-blue-600 dark:bg-blue-700 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-all touch-target" aria-label="Toggle Sidebar">
        <i class="fas fa-bars text-lg"></i>
    </button>

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

    <!-- Scripts -->
    <!-- Toast Notifications (must load before other scripts) -->
    @include('partials.toast-notifications')

    @stack('scripts')

    <script>
        // Theme management with system preference support
        (function() {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

            function applyTheme(themePreference) {
                const html = document.documentElement;

                if (themePreference === 'system') {
                    if (mediaQuery.matches) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                } else if (themePreference === 'dark') {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
            }

            function updateThemeButton(theme) {
                const themeIcon = document.getElementById('theme-icon');
                const themeLabel = document.getElementById('theme-label');
                const themeToggle = document.getElementById('theme-toggle');

                const icons = {
                    'light': 'fa-sun',
                    'dark': 'fa-moon',
                    'system': 'fa-desktop'
                };
                const labels = {
                    'light': 'Light',
                    'dark': 'Dark',
                    'system': 'System'
                };
                const titles = {
                    'light': 'Light Mode',
                    'dark': 'Dark Mode',
                    'system': 'System Mode'
                };

                if (themeIcon) {
                    // Remove all possible icon classes first
                    themeIcon.classList.remove('fa-sun', 'fa-moon', 'fa-desktop');
                    // Add the correct icon class
                    themeIcon.classList.add(icons[theme]);
                }
                if (themeLabel) {
                    themeLabel.textContent = labels[theme];
                }
                if (themeToggle) {
                    themeToggle.setAttribute('title', titles[theme]);
                }
            }

            // Listen for OS theme changes
            mediaQuery.addEventListener('change', () => {
                @auth
                    const userThemePreference = '{{ auth()->user()->theme_preference ?? 'light' }}';
                    if (userThemePreference === 'system') {
                        applyTheme('system');
                    }
                @else
                    const theme = localStorage.getItem('theme') || 'light';
                    if (theme === 'system') {
                        applyTheme('system');
                    }
                @endauth
            });

            // Listen for custom theme change events from sidebar
            document.addEventListener('themeChanged', function(e) {
                if (e.detail && e.detail.theme) {
                    updateThemeButton(e.detail.theme);
                    applyTheme(e.detail.theme);
                    @auth
                        currentTheme = e.detail.theme;
                    @else
                        currentTheme = e.detail.theme;
                    @endauth
                }
            });

            // Dark mode toggle - cycles through light -> dark -> system
            const themeToggle = document.getElementById('theme-toggle');
            @auth
                let currentTheme = '{{ auth()->user()->theme_preference ?? 'light' }}';
            @else
                let currentTheme = localStorage.getItem('theme') || 'light';
            @endauth

            themeToggle?.addEventListener('click', function() {
                let nextTheme;

                // Cycle through: light -> dark -> system -> light
                if (currentTheme === 'light') {
                    nextTheme = 'dark';
                } else if (currentTheme === 'dark') {
                    nextTheme = 'system';
                } else {
                    nextTheme = 'light';
                }

                applyTheme(nextTheme);
                updateThemeButton(nextTheme);

                @auth
                    // Save to backend for authenticated users
                    fetch('{{ route('profile.update-theme') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ theme_preference: nextTheme })
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              currentTheme = nextTheme;
                              // Dispatch event to update sidebar
                              document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: nextTheme } }));
                          }
                      });
                @else
                    localStorage.setItem('theme', nextTheme);
                    currentTheme = nextTheme;
                    // Dispatch event to update sidebar
                    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: nextTheme } }));
                @endauth
            });

            // Mobile sidebar toggle
            document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', function() {
                document.getElementById('sidebar')?.classList.toggle('hidden');
            });

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
        })();
    </script>
</body>
</html>

