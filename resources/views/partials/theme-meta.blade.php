{{--
    Theme metas read by resources/js/alpine/stores/theme.js. Every layout that loads app.js
    must include this, otherwise the store falls back to defaults and overrides theme-init.
--}}
<meta name="theme-preference" content="{{ $userTheme ?? \App\Enums\ThemePreference::default()->value }}">
<meta name="color-scheme-preference" content="{{ $userColorScheme ?? \App\Enums\ColorScheme::default()->value }}">
@auth
    <meta name="user-authenticated" content="true">
    <meta name="update-theme-url" content="{{ route('profile.update-theme') }}">
@endauth
