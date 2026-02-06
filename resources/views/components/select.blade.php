<select {{ $attributes->merge(['class' => 'px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition']) }}>
    {{ $slot }}
</select>

