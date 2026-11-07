{{--
    Table header cell with scope="col". For sortable columns pass `href` (the sort link) and
    `sort` = 'asc' | 'desc' on the active column; that sets aria-sort and the arrow icon.
    `width` accepts a fixed list so Tailwind can see every class.
--}}
@props([
    'align' => 'left',
    'width' => null,
    'href' => null,
    'sort' => null,
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
    $widthClass = match ((string) $width) {
        '8' => 'w-8', '10' => 'w-10', '12' => 'w-12', '16' => 'w-16', '20' => 'w-20', '24' => 'w-24',
        '28' => 'w-28', '32' => 'w-32', '36' => 'w-36', '40' => 'w-40', '48' => 'w-48', '64' => 'w-64',
        default => '',
    };
    $ariaSort = match ($sort) {
        'asc' => 'ascending',
        'desc' => 'descending',
        default => null,
    };
    $sortIcon = match ($sort) {
        'asc' => 'fa-sort-up',
        'desc' => 'fa-sort-down',
        default => 'fa-sort opacity-40',
    };
@endphp

<th scope="col" @if($ariaSort) aria-sort="{{ $ariaSort }}" @endif {{ $attributes->merge(['class' => trim("px-4 py-3 {$alignClass} text-xs font-semibold uppercase tracking-wider text-fg-muted {$widthClass}")]) }}>
    @if($href)
        <a href="{{ $href }}" class="inline-flex items-center gap-1.5 rounded hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            {{ $slot }}
            <i aria-hidden="true" class="fa-solid {{ $sortIcon }}"></i>
        </a>
    @else
        {{ $slot }}
    @endif
</th>
