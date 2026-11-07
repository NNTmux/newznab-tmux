{{-- Admin page title row: icon, title, optional subtitle and actions. Sits above the page's cards. --}}
@props([
    'title',
    'icon' => null,
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'admin-page-header workspace-page-header mb-5']) }}>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            @if($icon)
                <span class="admin-page-header__icon" aria-hidden="true">
                    <i class="{{ $icon }}"></i>
                </span>
            @endif
            <div class="min-w-0">
                <h1 class="break-words text-2xl font-semibold tracking-tight text-fg">{{ $title }}</h1>
                @if($subtitle)
                    <p class="mt-0.5 text-sm text-fg-muted">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if(isset($actions))
            <div class="admin-page-header__actions flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</header>
