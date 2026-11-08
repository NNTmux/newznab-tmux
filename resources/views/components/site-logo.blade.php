@props([
    'url' => null,
    'imageClass' => 'h-full w-full object-contain p-1.5',
])

@if(is_string($url) && $url !== '')
    <img src="{{ $url }}" alt="" class="{{ $imageClass }}">
@else
    <x-application-logo class="h-[70%] w-[70%]" />
@endif
