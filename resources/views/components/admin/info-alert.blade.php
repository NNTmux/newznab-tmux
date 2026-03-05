{{-- Admin blue info alert box --}}
<div class="px-6 py-4 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800">
    <div class="flex">
        <div class="shrink-0">
            <i class="fas fa-info-circle text-blue-500 dark:text-blue-400 text-xl mr-3"></i>
        </div>
        <div class="text-sm text-blue-700 dark:text-blue-300">
            {{ $slot }}
        </div>
    </div>
</div>

