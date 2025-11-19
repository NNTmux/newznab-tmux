@php
$colorClasses = match ($type) {
    'primary', '', null => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
    'success', '', null => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800',
    'danger' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border border-orange-200 dark:border-orange-800'
};
@endphp

<div class="alert alert-{{ $type }} alert-dismissable rounded-md my-4 p-4 flex justify-between gap-4 {{ $colorClasses }} transition-colors">
    <div class="message">
        {!! $message !!}
    </div>
    <button type="button" data-dismiss="alert" aria-hidden="true" class="hover:opacity-70 transition-opacity">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
        </svg>
    </button>
</div>
