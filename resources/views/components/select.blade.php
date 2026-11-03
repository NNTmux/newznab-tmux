@blaze(fold: true)
<select {{ $attributes->merge(['class' => 'min-h-11 w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:[color-scheme:dark]']) }}>
    {{ $slot }}
</select>
