{{-- Checkbox styled with the scheme accent. Pass a label via the slot to render it inline. --}}
@props([
    'label' => null,
])

@if($label || trim($slot) !== '')
    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-fg">
        <input type="checkbox" {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-line bg-surface text-accent focus:ring-primary-500 focus:ring-offset-canvas']) }}>
        <span>{{ $label ?? $slot }}</span>
    </label>
@else
    <input type="checkbox" {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-line bg-surface text-accent focus:ring-primary-500 focus:ring-offset-canvas']) }}>
@endif
