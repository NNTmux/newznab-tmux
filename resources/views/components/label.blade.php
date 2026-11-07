@blaze(fold: true)
<label {{ $attributes->merge(['class' => 'mb-1.5 block w-full text-sm font-semibold text-fg']) }}>
    {{ $slot }}
</label>
