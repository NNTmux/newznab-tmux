{{--
    Branded error layout. Error pages can render before the session/auth middleware runs
    (unmatched-route 404, maintenance 503) or while the database is down (500), so this
    layout must never call auth(), $site, settings or anything else backed by the DB.
    Sections: title, message, code (optional), description (optional), icon (optional).
    Pass actions through @extends: ['actions' => [['url' => …, 'label' => …, 'icon' => 'fa-…'], …]].
    The first action is the primary button; "Back to home" is always offered.
--}}
@php
    try {
        $errorPageAssets = app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']);
    } catch (\Throwable) {
        $errorPageAssets = null;
    }

    $homeUrl = url('/');
    $errorActions = array_values($actions ?? []);
    if (! in_array($homeUrl, array_column($errorActions, 'url'), true)) {
        $errorActions[] = ['url' => $homeUrl, 'label' => __('Back to home'), 'icon' => 'fa-house'];
    }

    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $retryAfterSeconds = is_numeric($retryAfter) ? (int) $retryAfter : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-color-scheme="{{ \App\Enums\ColorScheme::default()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">

    @include('partials.theme-init', ['static' => true])

    <title>@yield('title') · {{ config('app.name') }}</title>

    @if($errorPageAssets)
        {{ $errorPageAssets }}
    @else
        {{-- Built assets unavailable: keep the page readable with a tiny standalone style. --}}
        <style nonce="{{ csp_nonce() }}">
            body { margin: 0; font-family: system-ui, sans-serif; }
            .error-page { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; text-align: center; }
            .error-page__code { font-size: 4.5rem; font-weight: 800; margin: 0; color: #2563eb; }
            .error-page__actions a { display: inline-block; margin: 0.25rem; padding: 0.65rem 1.1rem; border-radius: 0.75rem; background: #2563eb; color: #fff; text-decoration: none; }
            .error-page__icon { display: none; }
        </style>
    @endif
</head>
<body class="font-sans antialiased">
    <main id="main-content" class="error-page guest-view-stack flex min-h-screen items-center justify-center px-4 py-12 sm:px-6">
        <section class="error-page__card w-full max-w-lg rounded-overlay border border-line bg-surface p-8 text-center shadow-overlay sm:p-10" aria-labelledby="error-title">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fg-muted">{{ config('app.name') }}</p>

            @hasSection('icon')
                <div class="error-page__icon mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-primary-100 text-xl text-link dark:bg-primary-950/60" aria-hidden="true">
                    <i aria-hidden="true" class="fa-solid @yield('icon')"></i>
                </div>
            @endif

            @hasSection('code')
                <p class="error-page__code mt-4 bg-gradient-to-br from-primary-500 to-primary-700 bg-clip-text text-7xl font-extrabold tracking-tight text-transparent tabular-nums dark:from-primary-300 dark:to-primary-500 sm:text-8xl">@yield('code')</p>
            @endif

            <h1 id="error-title" class="mt-4 text-2xl font-bold tracking-tight text-fg">@yield('message')</h1>

            @hasSection('description')
                <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-fg-muted">@yield('description')</p>
            @endif

            @if($retryAfterSeconds !== null)
                <p class="error-page__retry mt-3 text-sm font-medium text-fg">
                    {{ trans_choice('Please try again in :count second.|Please try again in :count seconds.', $retryAfterSeconds, ['count' => $retryAfterSeconds]) }}
                </p>
            @endif

            <div class="error-page__actions mt-8 flex flex-wrap items-center justify-center gap-3">
                @foreach($errorActions as $action)
                    <a href="{{ $action['url'] }}" @class([
                        'inline-flex min-h-11 items-center gap-2 rounded-control px-5 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2',
                        'bg-accent text-on-accent shadow-raised hover:bg-accent-hover' => $loop->first,
                        'border border-line bg-surface text-fg hover:bg-surface-hover' => ! $loop->first,
                    ])>
                        <i class="fa-solid {{ $action['icon'] }}" aria-hidden="true"></i>
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
