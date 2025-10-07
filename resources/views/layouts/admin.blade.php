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
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen flex">
        <!-- Admin Sidebar -->
        <aside class="hidden md:flex md:flex-col w-64 bg-gray-900 text-white">
            <div class="flex items-center justify-between p-4 border-b border-gray-800">
                <a href="{{ route('admin.index') }}" class="flex items-center space-x-2">
                    <i class="fas fa-cog text-2xl text-blue-500"></i>
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
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-800">{{ $page_title ?? 'Admin Dashboard' }}</h1>
                    <div class="flex items-center space-x-4">
                        <a href="{{ url('/') }}" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-home mr-1"></i> Back to Site
                        </a>
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                           class="text-red-600 hover:text-red-800">
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
                    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-800 rounded">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>

