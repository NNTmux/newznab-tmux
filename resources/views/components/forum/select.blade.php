@blaze(fold: true)
<div class="inline-block relative w-64">
    <select {{ $attributes->merge(['class' => 'block w-full appearance-none rounded-lg border border-gray-400 bg-white px-4 py-2 pr-8 leading-tight text-gray-900 shadow hover:border-gray-500 focus:outline-none focus:shadow-outline dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:[color-scheme:dark]']) }}>
        {{ $slot }}
    </select>
    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
            <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
        </svg>
    </div>
</div>
