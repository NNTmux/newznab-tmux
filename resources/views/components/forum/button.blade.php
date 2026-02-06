<button {{ $attributes->merge(['class' => 'bg-blue-500 text-white px-3 py-2 rounded-lg inline-block transition']) }}>
    {{ $slot }}
</button>
