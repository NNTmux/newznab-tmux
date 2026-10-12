@props(['release'])

@php
    $displayName = \App\Support\ReleaseNameDisplay::clean($release->searchname);
    $originalName = \App\Support\ReleaseNameDisplay::clean($release->name);
@endphp

<div class="text-gray-900 dark:text-gray-100 font-medium max-w-md wrap-break-word break-all" title="{{ $displayName }}">
    {{ $displayName }}
</div>
@if($originalName !== '' && $originalName !== $displayName)
    <div class="text-gray-500 dark:text-gray-400 text-xs mt-1 max-w-md truncate" title="{{ $originalName }}">
        {{ $originalName }}
    </div>
@endif
