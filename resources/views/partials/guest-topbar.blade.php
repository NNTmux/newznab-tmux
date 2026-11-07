{{-- Slim top bar for signed-out visitors on the main layout: brand, theme toggle, sign in / register. --}}
@php $guestTheme = $userTheme ?? \App\Enums\ThemePreference::default()->value; @endphp
<div class="container mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-3 px-3 sm:px-5 lg:px-8">
    <a href="{{ url($site->homeLink ?? '/') }}" class="flex min-w-0 items-center gap-2.5 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400">
        <span class="layout-sidebar__logo flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
            <x-site-logo :url="$siteLogoUrl ?? null" />
        </span>
        <span class="truncate font-semibold">{{ config('app.name') }}</span>
    </a>
    <div class="flex items-center gap-1.5">
        <button type="button" id="theme-toggle" class="touch-target inline-flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400"
                title="{{ ucfirst($guestTheme) }} Mode"
                aria-label="Change theme. Current theme: {{ ucfirst($guestTheme) }}">
            <i aria-hidden="true" id="theme-icon" class="fas {{ $guestTheme === 'dark' ? 'fa-moon' : ($guestTheme === 'system' ? 'fa-desktop' : 'fa-sun') }}"></i>
            <span id="theme-label" class="hidden text-xs font-medium sm:inline">{{ ucfirst($guestTheme) }}</span>
        </button>
        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
            <i aria-hidden="true" class="fa-solid fa-right-to-bracket mr-1"></i>{{ __('Sign in') }}
        </a>
        <a href="{{ route('register') }}" aria-label="{{ __('Register') }}" class="rounded-lg bg-accent px-3 py-2 text-sm font-semibold text-on-accent transition hover:bg-accent-hover">
            <i aria-hidden="true" class="fa-solid fa-user-plus mr-1"></i><span class="hidden sm:inline">{{ __('Register') }}</span>
        </a>
    </div>
</div>
