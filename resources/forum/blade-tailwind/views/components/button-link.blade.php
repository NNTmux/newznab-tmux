<a {{ $attributes->merge(['class' => 'bg-blue-500 hover:bg-blue-400 dark:bg-blue-600 dark:hover:bg-blue-500 text-white font-semibold hover:text-white px-3 py-2 rounded-md inline-block transition-colors']) }}>
    {{ $slot }}
</a>
