<select {{ $attributes->merge(['class' => 'px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500']) }}>
    {{ $slot }}
</select>

