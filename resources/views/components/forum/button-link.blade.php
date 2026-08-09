@blaze(fold: true)
<a {{ $attributes->merge(['class' => 'bg-primary-500 text-white px-3 py-2 rounded-lg inline-block transition']) }}>
    {{ $slot }}
</a>
