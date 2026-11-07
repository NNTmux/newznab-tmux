{{-- Navigation link that marks the current page with aria-current="page" (styled by the sidebar CSS). --}}
@props([
    'href',
    'active' => false,
    'icon' => null,
])

<a href="{{ $href }}" @if($active) aria-current="page" @endif {{ $attributes->class(['nav-link', 'nav-link--active' => $active]) }}>
    @if($icon)
        <i aria-hidden="true" class="{{ $icon }} fa-fw shrink-0"></i>
    @endif
    <span class="min-w-0 truncate">{{ $slot }}</span>
</a>
