<button {{ $attributes->merge(['class' => 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-3 py-2 rounded-md inline-block']) }}>
    {{ $slot }}
</button>
