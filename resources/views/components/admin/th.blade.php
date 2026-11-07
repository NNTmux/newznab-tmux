{{-- Admin table header cell: wrapper around the shared <x-th> (scope, aria-sort, sort links). --}}
@props([
    'align' => 'left',
    'width' => null,
    'href' => null,
    'sort' => null,
])

<x-th :align="$align" :width="$width" :href="$href" :sort="$sort" {{ $attributes }}>{{ $slot }}</x-th>
