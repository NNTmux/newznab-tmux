{{-- Admin data table with consistent thead styling --}}
@props([
    'striped' => false,
])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200 dark:divide-gray-700']) }}>
        @if(isset($head))
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    {{ $head }}
                </tr>
            </thead>
        @endif
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            {{ $slot }}
        </tbody>
    </table>
</div>

