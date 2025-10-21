<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-preference" content="system">

    <title>{{ config('app.name') }}</title>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <!-- Captcha Scripts -->
    @if(\App\Support\CaptchaHelper::isEnabled())
        @if(\App\Support\CaptchaHelper::getProvider() === 'turnstile')
            @if(function_exists('csp_nonce'))
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="{{ csp_nonce() }}" async defer></script>
            @else
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @endif
        @else
            @if(function_exists('csp_nonce'))
                <script src="https://www.google.com/recaptcha/api.js" nonce="{{ csp_nonce() }}" async defer></script>
            @else
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @endif
        @endif
    @endif
</head>
<body class="font-sans antialiased">
    @yield('content')

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>
