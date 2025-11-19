<button {{ $attributes->merge(['class' => 'bg-gray-300 hover:bg-gray-200 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-600 dark:text-gray-200 font-semibold px-3 py-2 rounded-md inline-block transition-colors']) }}>
    {{ $slot }}
</button>
