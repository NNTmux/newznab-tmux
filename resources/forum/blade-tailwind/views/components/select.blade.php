<div class="inline-block relative w-64">
    <select {{ $attributes->merge(['class' => 'block appearance-none w-full bg-white dark:bg-gray-800 border border-gray-400 dark:border-gray-600 hover:border-gray-500 dark:hover:border-gray-500 text-gray-900 dark:text-gray-100 px-4 py-2 pr-8 rounded shadow leading-tight focus:outline-none focus:shadow-outline transition-colors']) }}>
        {{ $slot }}
    </select>
</div>
